@echo off
echo Starting AI Facebook Content Tool Development Environment...
echo Running all services in one terminal using concurrently...

:: Dung npm (Node.js) de chay dong thoi tat ca cac tien trinh tren cung 1 terminal (console)
npx concurrently -n "API,QUEUE,CRON,VITE" -c "bgBlue.bold,bgGreen.bold,bgYellow.bold,bgMagenta.bold" ^
  "cd backend && php artisan serve" ^
  "cd backend && php artisan queue:work database --queue=facebook-publish,image-generation,video-generation,default --tries=3 --timeout=120" ^
  "cd backend && php artisan schedule:work" ^
  "cd frontend && npm run dev"
