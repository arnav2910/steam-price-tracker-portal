# Steam Tracker — RAG Chatbot

This folder contains the Python Retrieval-Augmented Generation (RAG) bot and
its FastAPI server wrapper. The bot translates natural-language questions into
SQL, queries the `steam_tracker` database, and returns human-readable answers.

---

## Quick Start

### 1. Install dependencies
```bash
cd steam_Tracker/rag
pip install -r requirements.txt
```

### 2. Create the `.env` file
```env
HF_TOKEN=your_hugging_face_token_here

# Optional — defaults work for a standard XAMPP setup:
DB_USER=root
DB_PASSWORD=
DB_HOST=localhost
DB_NAME=steam_tracker
```

Get a free HuggingFace token at https://huggingface.co → Settings → Access Tokens.

### 3. Start the API server
```bash
uvicorn chatbot_api:app --host 0.0.0.0 --port 8000 --reload
```

The server starts on **http://localhost:8000**.
Open `http://localhost:8000/docs` for the interactive Swagger UI.

### 4. Use the chatbot
Open **chatbot.php** in your browser. The PHP page makes AJAX calls to the
Python server at `http://localhost:8000/chat`.

---

## Example questions
- *"What is the lowest price for ARK Survival Ascended?"*
- *"Which game has the most positive reviews?"*
- *"Show me all Action games currently on sale."*

---

## Running the CLI bot (optional)
```bash
python rag_bot.py
```
This launches an interactive terminal loop for direct testing.
