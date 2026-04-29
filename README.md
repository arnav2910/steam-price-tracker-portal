# Steam Game Price and Review Insight Portal

A full-stack web application for exploring historical price fluctuations and community review trends across Steam games. The portal features a composite Buy Score recommendation engine, interactive Chart.js visualizations, a K-Means clustering ML engine, and an AI-powered chatbot backed by a Retrieval-Augmented Generation (RAG) pipeline using LLaMA 3.1.

---

## Table of Contents

- [Steam Game Price and Review Insight Portal](#steam-game-price-and-review-insight-portal)
  - [Table of Contents](#table-of-contents)
  - [Overview](#overview)
  - [Key Features](#key-features)
  - [Architecture](#architecture)
  - [Project Structure](#project-structure)
  - [Technology Stack](#technology-stack)
  - [Prerequisites](#prerequisites)
  - [Installation](#installation)
    - [1. Clone the Repository](#1-clone-the-repository)
    - [2. Start XAMPP Services](#2-start-xampp-services)
    - [3. Create the Database](#3-create-the-database)
    - [4. Configure the Database Connection](#4-configure-the-database-connection)
    - [5. Import Game Data](#5-import-game-data)
    - [6. Access the Application](#6-access-the-application)
  - [Database Schema](#database-schema)
  - [Data Import](#data-import)
  - [Running the Application](#running-the-application)
  - [AI Chatbot Setup](#ai-chatbot-setup)
    - [1. Install Python Dependencies](#1-install-python-dependencies)
    - [2. Configure Environment Variables](#2-configure-environment-variables)
    - [3. Start the RAG Server](#3-start-the-rag-server)
    - [4. Use the Chatbot](#4-use-the-chatbot)
  - [Buy Score Algorithm](#buy-score-algorithm)
    - [Price Score (0--100)](#price-score-0--100)
    - [Review Score (0--100)](#review-score-0--100)
    - [Adaptive Blending](#adaptive-blending)
    - [Score Labels](#score-labels)
  - [ML Engine](#ml-engine)
    - [K-Means Clustering](#k-means-clustering)
    - [Anomaly Detection (Hidden Gems)](#anomaly-detection-hidden-gems)
  - [Page Reference](#page-reference)
  - [Security Considerations](#security-considerations)
  - [Troubleshooting](#troubleshooting)
  - [License](#license)

---

## Overview

The Steam Game Price and Review Insight Portal aggregates historical pricing and user review data for 50+ Steam titles and presents it through a modern, dark-themed dashboard. Users can browse games by category, drill into individual game analytics, compare titles side-by-side, and receive data-driven purchase recommendations. An AI chatbot translates natural-language questions into live SQL queries against the database and returns human-readable answers.

---

## Key Features

- **Dashboard** -- Card-based grid layout with real-time statistics, category browsing, and global search.
- **Game Detail Pages** -- Price history line charts, review sentiment bar charts, discount badges, and recommendation breakdowns.
- **Buy Score Engine** -- Composite 0-100 score blending price positioning and review sentiment with adaptive weighting.
- **Game Comparison** -- Side-by-side comparison of two games across price, discount, reviews, and buy score with an overall verdict.
- **ML Clustering** -- K-Means clustering assigns games into Budget Hits, Standard Tier, and Premium Titles; anomaly detection flags hidden gems.
- **AI Chatbot** -- RAG pipeline powered by LLaMA 3.1 via HuggingFace, with LangChain orchestration and FastAPI serving.
- **Insight Queries** -- Pre-built analytical questions (cheapest game, biggest price drop, highest-reviewed) filterable by category.
- **User Accounts** -- Registration, login, session-based authentication, wishlists, and shopping carts.
- **Responsive Design** -- Fully responsive dark-themed UI built with vanilla CSS and CSS custom properties.

---

## Architecture

```
Browser (Client)
    |
    v
Apache (XAMPP) ---- PHP Pages ---- MySQL / MariaDB
                                       ^
                                       |
AI Chatbot (chatbot.php) --HTTP--> FastAPI Server (port 8000)
                                       |
                                   LangChain + LLaMA 3.1 (HuggingFace API)
                                       |
                                   MySQL via SQLAlchemy
```

The PHP frontend communicates directly with MySQL for all core operations. The AI chatbot page makes client-side fetch requests to a separate Python FastAPI server, which uses LangChain to generate SQL from natural language, executes it against the same MySQL database, and returns a synthesized answer.

---

## Project Structure

```
steam_Tracker/
|
|-- css/
|   +-- style.css                  # Global styles, design tokens, responsive layout
|
|-- data/
|   +-- [CSV files]                # Price and review CSV files (50 games, ~100 files)
|
|-- includes/
|   |-- db.php                     # MySQL connection and table auto-creation
|   |-- auth.php                   # Session management, login checks, badge counts
|   |-- header.php                 # Shared HTML head, navigation bar, user menu
|   +-- logic.php                  # Buy Score calculation function
|
|-- steam_game_headers_by_name/
|   +-- [Game images]              # Header images named by game title (.jpg)
|
|-- rag/
|   |-- rag_bot.py                 # RAG pipeline: SQL generation, execution, answer synthesis
|   |-- chatbot_api.py             # FastAPI server exposing /chat and /health endpoints
|   |-- requirements.txt           # Python dependencies for the RAG subsystem
|   +-- README.md                  # RAG-specific documentation
|
|-- index.php                      # Dashboard: hero section, stats, category chips, game grid
|-- game.php                       # Game detail: charts, buy score, reviews, recommendations
|-- results.php                    # Search results and insight query answers
|-- compare.php                    # Side-by-side game comparison with overlaid charts
|-- chatbot.php                    # AI chatbot interface
|-- questions.php                  # Insight Quest: pre-built analytical questions
|-- ml_engine.php                  # K-Means clustering and anomaly detection
|-- import.php                     # CSV data import and synchronization
|-- login.php                      # User login form
|-- register.php                   # User registration form
|-- logout.php                     # Session termination
|-- wishlist.php                   # Wishlist management page
|-- wishlist_action.php            # AJAX endpoint for wishlist add/remove
|-- cart.php                       # Shopping cart management page
|-- cart_action.php                # AJAX endpoint for cart add/remove
+-- README.md                     # This file
```

---

## Technology Stack

| Layer          | Technology                                                  |
| -------------- | ----------------------------------------------------------- |
| Web Server     | Apache 2.4 (XAMPP)                                          |
| Backend        | PHP 7.4+                                                    |
| Database       | MySQL / MariaDB (via `mysqli`)                              |
| Frontend       | HTML5, vanilla CSS, vanilla JavaScript                      |
| Charts         | Chart.js 4.4 (CDN)                                          |
| AI/ML Backend  | Python 3.9+, FastAPI, LangChain, HuggingFace (LLaMA 3.1 8B) |
| ORM (RAG only) | SQLAlchemy + mysql-connector-python                         |

---

## Prerequisites

- **XAMPP** with Apache and MySQL enabled
- **PHP 7.4** or higher
- **MySQL / MariaDB** (bundled with XAMPP)
- **Python 3.9+** (required only for the AI chatbot)
- **A HuggingFace API token** (required only for the AI chatbot)
- A modern web browser (Chrome, Firefox, Edge)

---

## Installation

### 1. Clone the Repository

```bash
git clone https://github.com/lazyflash99/steam-price-tracker-portal.git
```

Copy the `steam_Tracker` folder into your XAMPP `htdocs` directory:

```
C:\xampp\htdocs\steam_Tracker\
```

### 2. Start XAMPP Services

Open the XAMPP Control Panel and start **Apache** and **MySQL**.

### 3. Create the Database

1. Open **phpMyAdmin** at `http://localhost/phpmyadmin`
2. Create a new database named `steam_tracker`
3. The required tables (`games`, `price_history`, `review_history`, `users`, `wishlist`, `cart`) are **auto-created** by `includes/db.php` on first page load

If you prefer to create them manually, see the [Database Schema](#database-schema) section below.

### 4. Configure the Database Connection

The default connection in `includes/db.php` is:

| Parameter | Default Value   |
| --------- | --------------- |
| Host      | `localhost`     |
| Username  | `root`          |
| Password  | *(empty)*       |
| Database  | `steam_tracker` |

If your MySQL credentials differ, update the `mysqli_connect()` call in `includes/db.php`.

### 5. Import Game Data

1. Ensure CSV data files are placed in the `steam_Tracker/data/` directory
2. Navigate to `http://localhost/steam_Tracker/import.php`
3. Click **Start Import** to load all CSV data into the database
4. Optionally, navigate to `http://localhost/steam_Tracker/ml_engine.php` to run the clustering engine

### 6. Access the Application

Open your browser and navigate to:

```
http://localhost/steam_Tracker/
```

---

## Database Schema

The application uses six tables. The `games`, `users`, `wishlist`, and `cart` tables are auto-created by `includes/db.php`. The `price_history` and `review_history` tables are implicitly created during the import process.

```sql
-- Core game catalog
CREATE TABLE games (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(255) NOT NULL UNIQUE,
    category      VARCHAR(500),
    cluster_label VARCHAR(100),
    is_anomaly    BOOLEAN DEFAULT FALSE
);

-- Historical price data (one row per game per date)
CREATE TABLE price_history (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    game_id    INT NOT NULL REFERENCES games(id) ON DELETE CASCADE,
    price_date DATE NOT NULL,
    price      DECIMAL(10, 2) NOT NULL
);

-- Historical review counts (one row per game per month)
CREATE TABLE review_history (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    game_id     INT NOT NULL REFERENCES games(id) ON DELETE CASCADE,
    review_date DATE NOT NULL,
    pos_reviews INT DEFAULT 0,
    neg_reviews INT DEFAULT 0
);

-- User accounts
CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User wishlists
CREATE TABLE wishlist (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    user_id  INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    game_id  INT NOT NULL REFERENCES games(id) ON DELETE CASCADE,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_wishlist (user_id, game_id)
);

-- User shopping carts
CREATE TABLE cart (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    user_id  INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    game_id  INT NOT NULL REFERENCES games(id) ON DELETE CASCADE,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cart (user_id, game_id)
);
```

---

## Data Import

The import system (`import.php`) scans the `data/` directory for CSV files matching one of two naming patterns:

| File Type      | Pattern                      | CSV Columns                                |
| -------------- | ---------------------------- | ------------------------------------------ |
| Price history  | `GameName_AppID_prices.csv`  | `date, price`                              |
| Review history | `GameName_AppID_reviews.csv` | `date, pos_reviews, neg_reviews, category` |

The importer automatically creates game entries in the `games` table if they do not already exist, and populates `price_history` and `review_history` with the time-series data. Category tags are extracted from the review CSV and stored as pipe-delimited strings (e.g., `Action|Adventure|Survival`).

All prices in the dataset are denominated in **Indian Rupees (INR)**.

---

## Running the Application

1. Start Apache and MySQL from the XAMPP Control Panel
2. Navigate to `http://localhost/steam_Tracker/` in your browser
3. If the database is empty, go to **Sync Data** to import CSV files
4. Run the **ML Engine** to generate cluster labels and detect hidden gems
5. Browse games, view detailed analytics, compare titles, and explore insights

---

## AI Chatbot Setup

The AI chatbot requires a separate Python server to be running alongside the PHP application.

### 1. Install Python Dependencies

```bash
cd steam_Tracker/rag
pip install -r requirements.txt
```

### 2. Configure Environment Variables

Create a `.env` file in the `rag/` directory:

```env
HF_TOKEN=your_huggingface_api_token
DB_USER=root
DB_PASSWORD=
DB_HOST=localhost
DB_NAME=steam_tracker
```

You can obtain a HuggingFace API token from [huggingface.co/settings/tokens](https://huggingface.co/settings/tokens). The token must have access to the `meta-llama/Llama-3.1-8B-Instruct` model.

### 3. Start the RAG Server

```bash
cd steam_Tracker/rag
python chatbot_api.py
```

The server starts on `http://localhost:8000` by default. Verify it is running:

```bash
curl http://localhost:8000/health
```

### 4. Use the Chatbot

Navigate to `http://localhost/steam_Tracker/chatbot.php` in your browser. The chatbot translates natural-language questions into SQL, executes them against the live database, and returns synthesized answers. Example queries:

- "What is the lowest price for Hollow Knight?"
- "Which game has the highest positive reviews?"
- "Compare Baldur's Gate 3 and Hades II. Which should I buy?"
- "Show me all Action games under 1000 rupees"

The RAG pipeline includes SQL injection prevention via a forbidden-statement filter and query validation.

---

## Buy Score Algorithm

The Buy Score is a composite metric (0--100) that blends two signals:

### Price Score (0--100)

Evaluates the current price relative to the game's historical price range:

| Condition                                         | Score                          |
| ------------------------------------------------- | ------------------------------ |
| Current price at or below all-time low            | 100                            |
| Current price above the midpoint of min/max range | 20                             |
| Current price between low and midpoint            | 20--100 (linear interpolation) |

### Review Score (0--100)

Directly uses the positive review percentage (`positive / total * 100`).

### Adaptive Blending

The final score uses sentiment-aware weights that shift emphasis depending on community reception:

| Sentiment Bracket              | Review Weight | Price Weight |
| ------------------------------ | ------------- | ------------ |
| Overwhelmingly Positive (95%+) | 0.75          | 0.25         |
| Very Positive (80--94%)        | 0.65          | 0.35         |
| Positive (65--79%)             | 0.55          | 0.45         |
| Mixed (50--64%)                | 0.45          | 0.55         |
| Negative (30--49%)             | 0.35          | 0.65         |
| Overwhelmingly Negative (<30%) | 0.25          | 0.75         |

### Score Labels

| Score Range | Label         |
| ----------- | ------------- |
| 85--100     | Excellent Buy |
| 70--84      | Good Value    |
| 55--69      | Fair Deal     |
| 35--54      | Wait a Bit    |
| 0--34       | Avoid         |

---

## ML Engine

The ML Engine page (`ml_engine.php`) performs two operations:

### K-Means Clustering

Games are clustered into three tiers using Z-score normalized price and review sentiment data. The algorithm dynamically initializes centroids based on the 33rd and 66th percentiles of the dataset.

- **Budget Hits**: Bottom 33% by price
- **Standard Tier**: Middle 33% by price
- **Premium Titles**: Top 33% by price

The clustering enforces hard price boundaries, while K-Means iterations are run purely on the sentiment Z-score within each band to group games by community reception. Cluster labels are persisted in the `games` table and used by the recommendation engine.

### Anomaly Detection (Hidden Gems)

Hidden Gems are detected dynamically *per cluster* rather than using global thresholds. A game qualifies as a hidden gem if it meets both of the following criteria within its cluster:
1. **Exceptional Reception**: Review sentiment is greater than or equal to the cluster's mean sentiment plus 0.75 standard deviations.
2. **Value Pricing**: Price is at or below the cluster's median price (excluding free games).

This surfaces affordable, highly-rated titles that punch above their weight relative to their direct peers.

---

## Page Reference

| Page           | URL Path                       | Description                                                        |
| -------------- | ------------------------------ | ------------------------------------------------------------------ |
| Dashboard      | `/index.php`                   | Hero section, stats counters, category chips, paginated game grid  |
| Game Detail    | `/game.php?id={id}`            | Price/review charts, buy score meter, similar game recommendations |
| Search Results | `/results.php?q={query}`       | Filtered game results by name or category                          |
| Compare Games  | `/compare.php?g1={id}&g2={id}` | Side-by-side metrics, overlaid price chart, review charts, verdict |
| AI Chatbot     | `/chatbot.php`                 | Natural-language interface to the RAG pipeline                     |
| Insight Quest  | `/questions.php`               | Pre-built analytical questions with category filtering             |
| ML Engine      | `/ml_engine.php`               | Run K-Means clustering and anomaly detection                       |
| Sync Data      | `/import.php`                  | Import/update game data from CSV files                             |
| Login          | `/login.php`                   | User authentication                                                |
| Register       | `/register.php`                | Account creation                                                   |
| Wishlist       | `/wishlist.php`                | View and manage wishlisted games                                   |
| Cart           | `/cart.php`                    | View and manage cart items                                         |

---

## Security Considerations

- All user-facing output is escaped with `htmlspecialchars()` to prevent XSS
- Passwords are stored using PHP's `password_hash()` with bcrypt
- Session-based authentication with server-side session management
- The RAG pipeline filters generated SQL through a forbidden-statement regex that blocks `DROP`, `DELETE`, `UPDATE`, `INSERT`, `ALTER`, `CREATE`, `TRUNCATE`, and other mutating keywords
- Generated queries are validated to ensure they begin with `SELECT` and reference only known tables

---

## Troubleshooting

**Database connection errors**
Confirm MySQL is running in the XAMPP Control Panel. Verify the credentials in `includes/db.php` match your MySQL configuration.

**Empty dashboard after setup**
Navigate to Sync Data (`import.php`) and click Start Import. Ensure CSV files are present in the `data/` directory.

**Game images not displaying**
Verify that header images exist in `steam_game_headers_by_name/` with filenames matching the exact game name in the database (case-sensitive). Only `.jpg` format is supported.

**Charts not rendering**
Ensure your browser can reach the Chart.js CDN at `cdn.jsdelivr.net`. If behind a firewall or proxy, download Chart.js locally and update the script source in `includes/header.php`.

**AI chatbot not responding**
Confirm the Python RAG server is running on port 8000 (`python rag/chatbot_api.py`). Check that the `.env` file contains a valid `HF_TOKEN` and correct database credentials.

**Import failures**
Ensure CSV files follow the naming convention `GameName_AppID_prices.csv` or `GameName_AppID_reviews.csv`. Files that do not match this pattern are silently skipped.

---

## License

This project is proprietary. All rights reserved.