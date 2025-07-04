# appwatchdog

**AppWatchdog** is a lightweight and extensible application usage monitoring system developed during an internship project. It tracks and logs user activity across applications on Windows systems, helping analyze productivity metrics based on real-world app usage.

## Overview

AppWatchdog is designed to:
- Monitor which applications are in use.
- Measure **active** (user-interacting) vs **idle** time.
- Log data to a central **MySQL** database.
- Display aggregated insights via a **PHP-based web dashboard**.

This system is ideal for internal teams, researchers, or IT administrators interested in usage-based behavioral analytics.

---

## Technologies Used

### Backend (Monitoring Agent)
- **Python** – Core logic for real-time tracking
- `pyautogui`, `pynput` – Input activity detection
- `psutil`, `win32gui`, `win32process` – Process and window tracking
- `socket`, `getpass`, `datetime` – Machine/user info and timestamping

### Database
- **MySQL** – Centralized storage of usage logs
- Table schema with fields: `username`, `hostname`, `application`, `active_time`, `idle_time`, `timestamp`

### Web Dashboard
- **PHP** – Backend scripting for data queries
- **PDO (PHP Data Objects)** – Secure and efficient database access
- **HTML/CSS (basic)** – UI for filtering and displaying results

---

##  Features

-  Tracks **active vs idle** time per app
-  Uses **window focus & user input** to determine activity
-  Displays results with filters for:
  - Username
  - Hostname
  - Application
  - Date range
-  Modular & extensible for new metrics or environments
-  Optimized for **background execution** with minimal performance impact

---

## Sample Use Case
![image](https://github.com/user-attachments/assets/817adf8b-66c5-451e-9227-3d1e89e79370)

aggregated data between desired dates
![image](https://github.com/user-attachments/assets/31a460c1-94aa-46b2-8b08-3799da60c6b1)

generated csv file
![image](https://github.com/user-attachments/assets/db69b03a-1964-4641-89dd-de7a907f26b4)


An IT administrator wants to evaluate how users engage with productivity software versus entertainment apps. AppWatchdog collects this data passively and makes it easy to review daily, weekly, or custom usage trends.

---
