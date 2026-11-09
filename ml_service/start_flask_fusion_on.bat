@echo off
cd c:\laragon\www\capstoneApp\ml_service
set FLASK_ENV=production
set FLASK_DEBUG=0
set FUSION_SHADOW_ENABLED=true
echo Starting Flask with FUSION_SHADOW_ENABLED=true
python.exe unified_ml_api_with_model.py
