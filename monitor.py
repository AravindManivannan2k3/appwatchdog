import time
import socket
import win32process
import psutil
import win32gui
import getpass
import mysql.connector
import pyautogui
from collections import defaultdict
from datetime import datetime, date
from pynput import keyboard
import winreg as reg
import os
import sys

#idle duration:
IDLE_THRESHOLD = 300  # seconds

username = getpass.getuser()

# def ensure_startup_registered():
#     exe_path = os.path.realpath(sys.argv[0])
#     key = r"Software\Microsoft\Windows\CurrentVersion\Run"
#     name = "monitor"

#     try:
#         reg_key = reg.OpenKey(reg.HKEY_CURRENT_USER, key, 0, reg.KEY_READ)
#         value, _ = reg.QueryValueEx(reg_key, name)
#         reg.CloseKey(reg_key)
#         if value == exe_path:
#             return
#     except FileNotFoundError:
#         pass

#     try:
#         reg_key = reg.OpenKey(reg.HKEY_CURRENT_USER, key, 0, reg.KEY_SET_VALUE)
#         reg.SetValueEx(reg_key, name, 0, reg.REG_SZ, exe_path)
#         reg.CloseKey(reg_key)
#         print("App registered to run at startup.")
#     except Exception as e:
#         print(f"Failed to add to startup: {e}")

def get_active_window_title():
    try:
        hwnd = win32gui.GetForegroundWindow()
        _, pid = win32process.GetWindowThreadProcessId(hwnd)
        process = psutil.Process(pid)
        window_title = win32gui.GetWindowText(hwnd).strip()
        process_name = process.name().replace(".exe", "").lower()

        terminal_names = ['cmd', 'powershell', 'windows terminal', 'conhost']
        if process_name in terminal_names:
            return process_name.title()

        if window_title:
            for delimiter in [' - ', ' — ', ' | ']:
                if delimiter in window_title:
                    parts = window_title.split(delimiter)
                    app_name = parts[-1].strip()
                    if len(app_name) > 2:
                        return app_name

            return window_title

        return process_name.title()

    except Exception:
        return "Locked"

def initialize_db():
    conn = mysql.connector.connect(
        host='localhost',#remote server address
        user='root',        # your remote DB username
        password='',  # your remote DB password
        database='app_usage_db'
        )
    cursor = conn.cursor()

    cursor.execute('''
        CREATE TABLE IF NOT EXISTS app_usage (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255),
            hostname VARCHAR(255),
            application VARCHAR(1000),
            active_time TIME DEFAULT '00:00:00',
            idle_time TIME DEFAULT '00:00:00',
            timestamp DATETIME
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    ''')
    conn.commit()
    return conn


def format_timedelta_seconds(seconds):
    hours = seconds // 3600
    minutes = (seconds % 3600) // 60
    secs = seconds % 60
    return f"{hours:02}:{minutes:02}:{secs:02}"

def save_to_db(conn, hostname, username, usage_data):
    cursor = conn.cursor()
    current_date = date.today()

    for app, times in usage_data.items():
        active_seconds = times.get("active", 0)
        idle_seconds = times.get("idle", 0)

        if active_seconds == 0 and idle_seconds == 0:
            continue  # Skip if there's no usage

        formatted_active = format_timedelta_seconds(active_seconds)
        formatted_idle = format_timedelta_seconds(idle_seconds)

        cursor.execute('''
            SELECT id, active_time, idle_time FROM app_usage
            WHERE hostname = %s AND username = %s AND application = %s AND DATE(timestamp) = %s
            LIMIT 1
        ''', (hostname, username, app, current_date))

        row = cursor.fetchone()

        if row:
            record_id, existing_active, existing_idle = row
            h, m, s = map(int, str(existing_active).split(':'))
            existing_active_seconds = h * 3600 + m * 60 + s
            h, m, s = map(int, str(existing_idle).split(':'))
            existing_idle_seconds = h * 3600 + m * 60 + s

            new_active = existing_active_seconds + active_seconds
            new_idle = existing_idle_seconds + idle_seconds

            cursor.execute('''
                UPDATE app_usage
                SET active_time = %s, idle_time = %s, timestamp = %s
                WHERE id = %s
            ''', (format_timedelta_seconds(new_active), format_timedelta_seconds(new_idle), datetime.now(), record_id))
        else:
            cursor.execute('''
                INSERT INTO app_usage (hostname, username, application, active_time, idle_time, timestamp)
                VALUES (%s, %s, %s, %s, %s, %s)
            ''', (hostname, username, app, formatted_active, formatted_idle, datetime.now()))

    conn.commit()

def main():
    hostname = socket.gethostname()
    usage_time = defaultdict(lambda: {"active": 0, "idle": 0})
    current_app = get_active_window_title()
    start_time = time.time()
    conn = initialize_db()
    username = getpass.getuser()
    last_mouse_pos = pyautogui.position()
    last_input_time = time.time()
    idle_start_time = None

    def on_press(key):
        nonlocal last_input_time
        last_input_time = time.time()

    listener = keyboard.Listener(on_press=on_press)
    listener.start()

    try:
        while True:
            time.sleep(1)
            now = time.time()

            current_mouse_pos = pyautogui.position()
            if current_mouse_pos != last_mouse_pos:
                last_mouse_pos = current_mouse_pos
                last_input_time = now

            is_active = (now - last_input_time) < IDLE_THRESHOLD
            new_app = get_active_window_title()

            if not is_active and idle_start_time is None:
                idle_start_time = now
                active_duration = int(now - start_time)
                usage_time[current_app]["active"] += active_duration
                start_time = now

            elif is_active and idle_start_time is not None:
                idle_duration = int(now - idle_start_time)
                usage_time[current_app]["idle"] += idle_duration
                idle_start_time = None
                start_time = now

            if is_active and new_app != current_app:
                active_duration = int(now - start_time)
                usage_time[current_app]["active"] += active_duration

                save_to_db(conn, hostname, username, {current_app: usage_time[current_app]},)
                usage_time[current_app] = {"active": 0, "idle": 0}

                current_app = new_app
                start_time = now

    except KeyboardInterrupt:
        now = time.time()
        if (now - last_input_time) < IDLE_THRESHOLD:
            active_duration = int(now - start_time)
            usage_time[current_app]["active"] += active_duration
        else:
            if idle_start_time is not None:
                idle_duration = int(now - idle_start_time)
                usage_time[current_app]["idle"] += idle_duration

        save_to_db(conn, hostname, username, usage_time)

        try:
            conn.close()
        except Exception as e:
            print(f"Error closing DB connection: {e}")

        listener.stop()


if __name__ == "__main__":
    #ensure_startup_registered()
    main()