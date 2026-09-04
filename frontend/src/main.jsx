import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import App from './App.jsx'
import './index.css'
import toast from 'react-hot-toast'

// Override default alert
window.alert = function(msg) {
  if (!msg) return;
  const lowerMsg = String(msg).toLowerCase();
  if (lowerMsg.includes('lỗi') || lowerMsg.includes('không thể') || lowerMsg.includes('thất bại')) {
    toast.error(msg);
  } else {
    toast.success(msg);
  }
};

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <BrowserRouter>
      <App />
    </BrowserRouter>
  </StrictMode>,
)
