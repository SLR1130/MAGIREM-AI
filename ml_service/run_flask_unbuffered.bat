@echo off
setlocal enabledelayedexpansion
set PYTHONUNBUFFERED=1
cd /d c:\laragon\www\capstoneApp\ml_service
python unified_ml_api_with_model.py
pause
