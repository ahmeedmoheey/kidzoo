@echo off
setlocal
cd /d "%~dp0"
"%~dp0venv311\Scripts\python.exe" -m uvicorn api:app --host 127.0.0.1 --port 8001
