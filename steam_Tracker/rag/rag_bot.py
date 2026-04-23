import os
import re
import sys
from dotenv import load_dotenv
from langchain_community.utilities import SQLDatabase
from langchain_huggingface import ChatHuggingFace, HuggingFaceEndpoint
from langchain_community.tools import QuerySQLDatabaseTool
from langchain_core.prompts import PromptTemplate
from langchain_core.output_parsers import StrOutputParser

# ==========================================
# 1. API KEY SETUP (Using .env file)
# ==========================================
load_dotenv()
hf_token = os.getenv("HF_TOKEN")

if not hf_token:
    raise ValueError("Missing HF_TOKEN! Please check your .env file.")

os.environ["HUGGINGFACEHUB_API_TOKEN"] = hf_token

# ==========================================
# 2. DATABASE CONNECTION
# ==========================================
DB_USER = os.getenv("DB_USER", "root")
DB_PASSWORD = os.getenv("DB_PASSWORD", "")
DB_HOST = os.getenv("DB_HOST", "localhost")
DB_NAME = os.getenv("DB_NAME", "steam_tracker")

mysql_uri = f"mysql+mysqlconnector://{DB_USER}:{DB_PASSWORD}@{DB_HOST}/{DB_NAME}"

try:
    db = SQLDatabase.from_uri(mysql_uri, sample_rows_in_table_info=3)
    print("[OK] Connected to database:", DB_NAME)
except Exception as e:
    print(f"[FATAL] Database connection failed: {e}")
    sys.exit(1)


def _fetch_live_schema() -> str:
    """Pull the real schema from the connected database so the prompt
    is always in sync with the actual tables and columns."""
    try:
        return db.get_table_info()
    except Exception:
        # Fallback: hardcoded schema that matches import.php exactly
        return """
Table: games
  - id          INT, PRIMARY KEY, AUTO_INCREMENT
  - name        VARCHAR  (game title, e.g. 'ARK Survival Ascended')
  - category    VARCHAR  (pipe-delimited tags, e.g. 'Action|Adventure|Survival')

Table: price_history
  - game_id     INT, FOREIGN KEY -> games.id
  - price_date  DATE
  - price       DECIMAL  (price in Indian Rupees / INR)

Table: review_history
  - game_id     INT, FOREIGN KEY -> games.id
  - review_date DATE
  - pos_reviews INT  (positive review count for that month)
  - neg_reviews INT  (negative review count for that month)
"""


LIVE_SCHEMA = _fetch_live_schema()

# ==========================================
# 3. LLM INITIALIZATION
# ==========================================
hf_llm = HuggingFaceEndpoint(
    repo_id="meta-llama/Llama-3.1-8B-Instruct",
    task="text-generation",
    temperature=0.1,
    do_sample=False,
    max_new_tokens=512,
)
llm = ChatHuggingFace(llm=hf_llm)
execute_query = QuerySQLDatabaseTool(db=db)

# ==========================================
# 4. CUSTOM PROMPTS
# ==========================================

# PROMPT 1 — SQL Generator
# Includes the live schema + few-shot examples so the LLM
# produces correct JOINs and column references every time.
sql_generation_prompt = PromptTemplate.from_template(
    """You are a MySQL expert. Your ONLY job is to write a single, valid
MySQL SELECT query that answers the user's question.

=== DATABASE SCHEMA ===
{schema}

=== RELATIONSHIPS ===
- games.id  <-->  price_history.game_id
- games.id  <-->  review_history.game_id

=== IMPORTANT RULES ===
1. Join games to price_history  : ON games.id = price_history.game_id
2. Join games to review_history : ON games.id = review_history.game_id
3. Game names use LIKE '%%keyword%%' for fuzzy matching (never bare =).
4. Prices are in Indian Rupees (INR). Never convert to USD.
5. Output ONLY the raw SQL query starting with SELECT.
   No markdown, no explanations, no apologies, no backticks.
6. Always add LIMIT 20 unless the user explicitly asks for all rows.
7. Use column aliases so the output is human-readable
   (e.g. SELECT name AS game_name).

=== EXAMPLE QUERIES ===

-- "What is the lowest price for ARK Survival Ascended?"
SELECT g.name AS game_name, MIN(p.price) AS lowest_price
FROM games g
JOIN price_history p ON g.id = p.game_id
WHERE g.name LIKE '%%ARK%%Survival%%'
GROUP BY g.name
LIMIT 1;

-- "Show the latest price of every game"
SELECT g.name AS game_name, p.price AS latest_price, p.price_date
FROM games g
JOIN price_history p ON g.id = p.game_id
WHERE p.price_date = (SELECT MAX(p2.price_date)
                      FROM price_history p2
                      WHERE p2.game_id = g.id)
ORDER BY g.name
LIMIT 20;

-- "Which game has the most positive reviews overall?"
SELECT g.name AS game_name, SUM(r.pos_reviews) AS total_positive
FROM games g
JOIN review_history r ON g.id = r.game_id
GROUP BY g.name
ORDER BY total_positive DESC
LIMIT 1;

-- "Show price history for Hades"
SELECT g.name AS game_name, p.price_date, p.price
FROM games g
JOIN price_history p ON g.id = p.game_id
WHERE g.name LIKE '%%Hades%%'
ORDER BY p.price_date ASC
LIMIT 20;

-- "Has any game received more negative reviews than positive in a single month?"
SELECT g.name AS game_name, r.review_date, r.pos_reviews, r.neg_reviews
FROM games g
JOIN review_history r ON g.id = r.game_id
WHERE r.neg_reviews > r.pos_reviews
ORDER BY r.review_date DESC
LIMIT 20;

-- "What categories does Terraria belong to?"
SELECT name AS game_name, category
FROM games
WHERE name LIKE '%%Terraria%%'
LIMIT 1;

-- "Which games are in the Action category?"
SELECT name AS game_name, category
FROM games
WHERE category LIKE '%%Action%%'
ORDER BY name
LIMIT 20;

-- "What is the average price of Balatro?"
SELECT g.name AS game_name, ROUND(AVG(p.price), 2) AS avg_price
FROM games g
JOIN price_history p ON g.id = p.game_id
WHERE g.name LIKE '%%Balatro%%'
GROUP BY g.name
LIMIT 1;

-- "Compare Hollow Knight and Terraria. Which one should I buy?"
SELECT
    g.name AS game_name,
    latest_p.price AS current_price,
    MIN(p.price) AS lowest_ever_price,
    ROUND(AVG(p.price), 2) AS avg_price,
    SUM(r.pos_reviews) AS total_positive,
    SUM(r.neg_reviews) AS total_negative,
    ROUND(SUM(r.pos_reviews) * 100.0 / (SUM(r.pos_reviews) + SUM(r.neg_reviews)), 1) AS positive_pct
FROM games g
JOIN price_history p ON g.id = p.game_id
JOIN review_history r ON g.id = r.game_id
JOIN (
    SELECT game_id, price
    FROM price_history ph
    WHERE ph.price_date = (SELECT MAX(price_date) FROM price_history WHERE game_id = ph.game_id)
) latest_p ON g.id = latest_p.game_id
WHERE g.name LIKE '%%Hollow Knight%%' OR g.name LIKE '%%Terraria%%'
GROUP BY g.name, latest_p.price;

-- "Which game has a better price-to-review ratio?"
SELECT
    g.name AS game_name,
    latest_p.price AS current_price,
    SUM(r.pos_reviews) AS total_positive,
    ROUND(SUM(r.pos_reviews) * 100.0 / (SUM(r.pos_reviews) + SUM(r.neg_reviews)), 1) AS positive_pct,
    g.category
FROM games g
JOIN price_history p ON g.id = p.game_id
JOIN review_history r ON g.id = r.game_id
JOIN (
    SELECT game_id, price
    FROM price_history ph
    WHERE ph.price_date = (SELECT MAX(price_date) FROM price_history WHERE game_id = ph.game_id)
) latest_p ON g.id = latest_p.game_id
GROUP BY g.name, latest_p.price, g.category
ORDER BY positive_pct DESC
LIMIT 10;

=== USER QUESTION ===
{question}

SQL Query:"""
)

# PROMPT 2 — Natural-language answer synthesizer
answer_prompt = PromptTemplate.from_template(
    """You are a friendly and opinionated gaming assistant for a Steam
price-tracking website. Given the user's question, the SQL query that was run,
and the raw database result, write a helpful natural-language answer.

RULES:
1. All prices are in Indian Rupees (INR / ₹). NEVER use the dollar sign ($).
2. If the result contains "Decimal(...)", just extract the number.
3. If the result is empty or None, say "I couldn't find any matching data."
4. Do NOT show raw SQL or technical jargon to the user.
5. COMPARISON & RECOMMENDATION RULE — This is CRITICAL:
   When the user asks to compare games or asks "which should I buy",
   you MUST:
   a) Briefly compare the key metrics (price, reviews, sentiment %).
   b) End with a CLEAR, DECISIVE recommendation like:
      "Based on the data, I'd recommend buying <Game> because ..."
   Never leave the user without a verdict. Pick a winner based on:
   - Better review sentiment (positive %)
   - Lower price or better value
   - Mention if one game is at a historic low price
6. Keep your answer concise but complete — 2-5 sentences.

Question: {question}
SQL Query: {query}
SQL Result: {result}

Answer:"""
)

# ==========================================
# 5. SQL CLEANING & VALIDATION
# ==========================================
_FORBIDDEN = re.compile(
    r"\b(DROP|DELETE|UPDATE|INSERT|ALTER|CREATE|TRUNCATE|REPLACE|GRANT|REVOKE)\b",
    re.IGNORECASE,
)


def clean_sql(raw_output: str) -> str:
    """Extract a clean SELECT statement from the LLM output."""
    # Strip markdown code fences
    cleaned = raw_output.replace("```sql", "").replace("```", "").strip()

    # Grab everything from the first SELECT to the end
    match = re.search(r"(SELECT\b.+)", cleaned, re.IGNORECASE | re.DOTALL)
    if not match:
        raise ValueError("LLM did not produce a valid SELECT query.")
    cleaned = match.group(1).strip()

    # Keep only the first statement (drop anything after a semicolon)
    cleaned = cleaned.split(";")[0].strip()

    # Safety: reject anything that mutates the database
    if _FORBIDDEN.search(cleaned):
        raise ValueError("Generated query contains forbidden statements.")

    return cleaned


def validate_query(sql: str) -> bool:
    """Basic sanity checks before we hit the database."""
    upper = sql.upper().strip()
    if not upper.startswith("SELECT"):
        return False
    # Must reference at least one known table
    known_tables = {"games", "price_history", "review_history"}
    return any(t in sql.lower() for t in known_tables)


# ==========================================
# 6. RAG PIPELINE
# ==========================================
MAX_RETRIES = 2


def ask_question(user_question: str) -> str:
    """End-to-end pipeline: question -> SQL -> execute -> answer.
    This function can be imported and called from main.py / FastAPI.
    """
    print(f"\n{'='*50}")
    print(f"[QUESTION] {user_question}")
    print(f"{'='*50}")

    last_error = None
    for attempt in range(1, MAX_RETRIES + 1):
        try:
            # --- Step 1: Generate SQL ----------------------------------
            sql_chain = sql_generation_prompt | llm | StrOutputParser()
            raw_sql = sql_chain.invoke({
                "question": user_question,
                "schema": LIVE_SCHEMA,
            })
            clean_query = clean_sql(raw_sql)
            print(f"[SQL  try {attempt}] {clean_query}")

            if not validate_query(clean_query):
                raise ValueError("Query failed validation (not a SELECT or unknown table).")

            # --- Step 2: Execute ---------------------------------------
            db_result = execute_query.invoke(clean_query)
            print(f"[DB RESULT] {db_result}")

            # Handle empty results
            if not db_result or db_result.strip() in ("", "[]", "None"):
                return "I couldn't find any matching data for your question. Try rephrasing it or checking the game name."

            # --- Step 3: Synthesize answer -----------------------------
            answer_chain = answer_prompt | llm | StrOutputParser()
            final_answer = answer_chain.invoke({
                "question": user_question,
                "query": clean_query,
                "result": db_result,
            })
            return final_answer.strip()

        except Exception as e:
            last_error = e
            print(f"[ERROR try {attempt}] {e}")
            if attempt < MAX_RETRIES:
                print("[RETRYING with a fresh LLM call...]")

    return (
        f"Sorry, I wasn't able to answer that after {MAX_RETRIES} attempts. "
        f"Last error: {last_error}"
    )


# ==========================================
# 7. INTERACTIVE LOOP
# ==========================================
if __name__ == "__main__":
    print("\n" + "=" * 60)
    print("  STEAM TRACKER — RAG Chat Bot")
    print("  Type your question about games, prices, or reviews.")
    print("  Type 'quit' or 'exit' to stop.")
    print("=" * 60 + "\n")

    while True:
        try:
            question = input("You: ").strip()
        except (EOFError, KeyboardInterrupt):
            print("\nGoodbye!")
            break

        if not question:
            continue
        if question.lower() in ("quit", "exit", "q"):
            print("Goodbye!")
            break

        answer = ask_question(question)
        print(f"\n🤖 Bot: {answer}\n")