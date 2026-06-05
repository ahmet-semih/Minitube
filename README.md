# MiniTube - A YouTube Clone

A simplified video-sharing web application built to demonstrate relational database design, user authentication, subscription mapping, and nested comment threads.

Developed as a Term Project for **CSE348: Database Management Systems (Spring 2026)**.

---

## 📂 Project Architecture

The application is structured into three clear layers to separate database files, user interface templates, and backend processing logic:

* **`data/`**: Stores raw text files utilized to seed the database and the generated compilation script containing table inserts.
* **`html/`**: Contains presentation templates handling the interface layouts, such as the registration gate, dashboard view, video window, and custom workspace portal.
* **`php/`**: Contains server scripts managing relational actions, view counters, user sessions, and database connections.

---

## 🚀 Setup & Setup Blueprint

### Prerequisites
* Web Server framework supporting PHP
* MySQL Relational Database Server

### Quick Start
1. Place the project directory directly into your local web server's root folder.
2. Verify that your database connection constants match your local server environment parameters.
3. Open your favorite web browser and navigate directly to the index page (`/html/index.html`).
4. Click the initialization button to execute the installation routine, create the schemas, insert seed data, and redirect to the authentication screen.
