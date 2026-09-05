@echo off
echo Starting AI Facebook Content Tool Development Environment...

:: 1. Start Laravel Backend (API)
start "Backend API" cmd /c "cd backend && php artisan serve"

:: 2. Start Laravel Queue Worker (Handles ALL background jobs: image, post, etc.)
start "Queue Worker" cmd /c "cd backend && php artisan queue:work database --queue=facebook-publish,image-generation,default --tries=3 --timeout=120"

:: 3. Start Laravel Scheduler (Checks for scheduled posts every minute)
start "Scheduler" cmd /c "cd backend && php artisan schedule:work"

:: 4. Start React Frontend
start "Frontend React" cmd /c "cd frontend && npm run dev"

echo All services have been started in separate windows!
exit
