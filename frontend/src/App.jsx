import { useEffect, useState } from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import { checkHealth } from './api';
import ContentGenerator from './pages/ContentGenerator';
import PostList from './pages/PostList';
import PostEditor from './pages/PostEditor';
import PostPublish from './pages/PostPublish';
import BrandList from './pages/BrandList';
import BrandEditor from './pages/BrandEditor';
import TemplateList from './pages/TemplateList';
import TemplateEditor from './pages/TemplateEditor';
import FacebookPages from './pages/FacebookPages';
import PublicationHistory from './pages/PublicationHistory';
import Settings from './pages/Settings';
import Dashboard from './pages/Dashboard';
import QueueMonitor from './pages/QueueMonitor';
import CampaignList from './pages/CampaignList';
import CampaignWizard from './pages/CampaignWizard';
import MediaLibrary from './pages/MediaLibrary';
import ImageStudio from './pages/ImageStudio';
import VideoStudioPage from './pages/VideoStudioPage';
import { Toaster } from 'react-hot-toast';
import AppLayout from './components/layout/AppLayout';
import './styles/design-tokens.css';
import './App.css'; // Keep existing styles for inner pages

function App() {
  const [healthStatus, setHealthStatus] = useState('Đang kiểm tra...');
  const [loading, setLoading] = useState(true);

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
    <AppLayout healthStatus={healthStatus}>
      <Toaster position="top-right" />
      <Routes>
        <Route path="/" element={<Navigate to="/dashboard" replace />} />
        <Route path="/dashboard" element={<Dashboard />} />
        <Route path="/queue" element={<QueueMonitor />} />
        <Route path="/campaigns" element={<CampaignList />} />
        <Route path="/campaigns/new" element={<CampaignWizard />} />
        <Route path="/media" element={<MediaLibrary />} />
        <Route path="/create-content" element={<ContentGenerator />} />
        <Route path="/posts" element={<PostList />} />
        <Route path="/posts/new" element={<PostEditor />} />
        <Route path="/posts/:id/edit" element={<PostEditor />} />
        <Route path="/posts/:id/publish" element={<PostPublish />} />
        <Route path="/posts/:id/publications" element={<PublicationHistory />} />
        <Route path="/brands" element={<BrandList />} />
        <Route path="/brands/new" element={<BrandEditor />} />
        <Route path="/brands/:id/edit" element={<BrandEditor />} />
        <Route path="/brands/:brandId/templates" element={<TemplateList />} />
        <Route path="/brands/:brandId/templates/new" element={<TemplateEditor />} />
        <Route path="/brands/:brandId/templates/:id/edit" element={<TemplateEditor />} />
        <Route path="/facebook-pages" element={<FacebookPages />} />
        <Route path="/publications" element={<PublicationHistory />} />
        <Route path="/image-studio" element={<ImageStudio />} />
        <Route path="/video-studio" element={<VideoStudioPage />} />
        <Route path="/settings" element={<Settings />} />
      </Routes>
    </AppLayout>
  );
}

export default App;
