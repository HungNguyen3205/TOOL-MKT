import { useEffect, useState } from 'react';
import { Routes, Route, Link, useLocation, Navigate } from 'react-router-dom';
import { checkHealth } from './api';
import ContentGenerator from './pages/ContentGenerator';
import PostList from './pages/PostList';
import PostEditor from './pages/PostEditor';
import BrandList from './pages/BrandList';
import BrandEditor from './pages/BrandEditor';
import TemplateList from './pages/TemplateList';
import TemplateEditor from './pages/TemplateEditor';
import FacebookPages from './pages/FacebookPages';
import './App.css';

function App() {
  const [healthStatus, setHealthStatus] = useState('Đang kiểm tra...');
  const [loading, setLoading] = useState(true);
  const location = useLocation();

  useEffect(() => {
    const fetchHealth = async () => {
      try {
        const data = await checkHealth();
        if (data.success) {
          setHealthStatus('Hệ thống hoạt động (' + data.data.application + ')');
        } else {
          setHealthStatus('Hệ thống có lỗi phản hồi.');
        }
      } catch (error) {
        setHealthStatus('Không thể kết nối backend.');
      } finally {
        setLoading(false);
      }
    };

    fetchHealth();
  }, []);

  return (
    <div className="container">
      <header className="header">
        <h1>AI Facebook Content Tool</h1>
      </header>
      
      <div className="sidebar">
        <nav>
          <ul>
            <li className={location.pathname === '/posts' ? 'active-nav' : ''}>
              <Link to="/posts">Bài viết</Link>
            </li>
            <li className={location.pathname === '/create-content' ? 'active-nav' : ''}>
              <Link to="/create-content">Tạo nội dung AI</Link>
            </li>
            <li className={location.pathname.startsWith('/brands') ? 'active-nav' : ''}>
              <Link to="/brands">Thương hiệu</Link>
            </li>
            <li className={location.pathname === '/facebook-pages' ? 'active-nav' : ''}>
              <Link to="/facebook-pages">Facebook Pages</Link>
            </li>
            <li>Cài đặt (Chưa mở)</li>
          </ul>
        </nav>
        <div className="sidebar-status">
          <h4>Trạng thái API Backend</h4>
          <p className={loading ? 'loading' : (healthStatus.includes('hoạt động') ? 'success' : 'error')}>
            {healthStatus}
          </p>
        </div>
      </div>

      <main className="main-content">
        <Routes>
          <Route path="/" element={<Navigate to="/posts" replace />} />
          <Route path="/create-content" element={<ContentGenerator />} />
          <Route path="/posts" element={<PostList />} />
          <Route path="/posts/new" element={<PostEditor />} />
          <Route path="/posts/:id/edit" element={<PostEditor />} />
          <Route path="/brands" element={<BrandList />} />
          <Route path="/brands/new" element={<BrandEditor />} />
          <Route path="/brands/:id/edit" element={<BrandEditor />} />
          <Route path="/brands/:brandId/templates" element={<TemplateList />} />
          <Route path="/brands/:brandId/templates/new" element={<TemplateEditor />} />
          <Route path="/brands/:brandId/templates/:templateId/edit" element={<TemplateEditor />} />
          <Route path="/facebook-pages" element={<FacebookPages />} />
        </Routes>
      </main>
    </div>
  );
}

export default App;
