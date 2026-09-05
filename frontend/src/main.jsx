import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import App from './App.jsx'
import './index.css'
import toast from 'react-hot-toast'

window.alert = function (msg) {
  if (!msg) return;

  const message = String(msg);
  const lowerMsg = message.toLowerCase();

  const errorKeywords = [
    'lỗi',
    'không thể',
    'thất bại',
    'không hợp lệ',
    'invalid',
    'validation',
    'thiếu',
    'hết hạn',
  ];

  const isError = errorKeywords.some(keyword =>
    lowerMsg.includes(keyword)
  );

  if (isError) {
    toast.error(message);
  } else {
    toast.success(message);
  }
};

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <BrowserRouter>
      <App />
    </BrowserRouter>
  </StrictMode>,
)
