import os
import sys

sys.path.insert(0, os.path.dirname(__file__))

from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field

from rag_bot import ask_question

app = FastAPI(
    title="SteamTracker RAG Chatbot API",
    description="Natural-language queries over the Steam price/review database.",
    version="1.0.0",
)

# Allowing requests from the local PHP app (XAMPP / any localhost origin)
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["POST", "GET", "OPTIONS"],
    allow_headers=["*"],
)

class ChatRequest(BaseModel):
    question: str = Field(..., min_length=1, max_length=1000,
                          description="Natural-language question about Steam games.")

class ChatResponse(BaseModel):
    answer: str
    error: bool = False

@app.get("/health", tags=["status"])
def health_check():
    """Quick liveness probe."""
    return {"status": "ok", "service": "SteamTracker RAG API"}


@app.post("/chat", response_model=ChatResponse, tags=["chat"])
def chat(req: ChatRequest):
    """
    Accepts a natural-language question, generates SQL via LLaMA 3.1,
    queries the steam_tracker database, and returns a synthesised answer.
    """
    question = req.question.strip()
    if not question:
        raise HTTPException(status_code=400, detail="Question must not be empty.")

    answer = ask_question(question)
    return ChatResponse(answer=answer)
