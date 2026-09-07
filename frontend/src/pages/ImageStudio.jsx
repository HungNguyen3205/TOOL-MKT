import React, { useState, useEffect } from 'react';
import { fetchPosts, generatePostImage, fetchPostImageStatus } from '../api/posts';
import toast from 'react-hot-toast';

const ImageStudio = () => {
  const [posts, setPosts] = useState([]);
  const [selectedPostId, setSelectedPostId] = useState('');
  const [selectedPost, setSelectedPost] = useState(null);
  
  const [loading, setLoading] = useState(false);
  const [generating, setGenerating] = useState(false);
  
  // Polling state
  const [pollingInterval, setPollingInterval] = useState(null);
  
  useEffect(() => {
    loadPosts();
    return () => {
      if (pollingInterval) clearInterval(pollingInterval);
    };
  }, []);

  const loadPosts = async () => {
    setLoading(true);
    try {
      const res = await fetchPosts({ limit: 50 });
      const loadedPosts = res.data || [];
      setPosts(loadedPosts);
      
      // Khôi phục trạng thái từ sessionStorage
      const savedPostId = sessionStorage.getItem('imageStudioSelectedPostId');
      if (savedPostId) {
        const post = loadedPosts.find(p => p.id === parseInt(savedPostId));
        if (post) {
          setSelectedPostId(savedPostId);
          setSelectedPost(post);
          if (post.status === 'generating_image') {
            startPolling(post.id);
            setGenerating(true);
          }
        }
      }
    } catch (err) {
      toast.error('Lỗi tải danh sách bài viết');
    } finally {
      setLoading(false);
    }
  };

  const handleSelectPost = (e) => {
    const id = e.target.value;
    setSelectedPostId(id);
    sessionStorage.setItem('imageStudioSelectedPostId', id);
    const post = posts.find(p => p.id === parseInt(id));
    setSelectedPost(post);
    if (pollingInterval) clearInterval(pollingInterval);
    
    // If post has processing image, start polling
    if (post && post.status === 'generating_image') {
       startPolling(post.id);
    }
  };

  const startPolling = (postId) => {
    if (pollingInterval) clearInterval(pollingInterval);
    const interval = setInterval(async () => {
      try {
        const statusRes = await fetchPostImageStatus(postId);
        if (statusRes.data?.status === 'completed') {
          toast.success('Tạo ảnh thành công!');
          clearInterval(interval);
          setGenerating(false);
          // Reload posts to get updated image URL
          loadPosts().then(() => {
             // update selected post silently if possible, but loadPosts will refresh the list
          });
        } else if (statusRes.data?.status === 'failed') {
          toast.error('Tạo ảnh thất bại.');
          clearInterval(interval);
          setGenerating(false);
        }
      } catch (err) {
        console.error('Polling error', err);
      }
    }, 5000);
    setPollingInterval(interval);
  };

  const handleGenerate = async () => {
    if (!selectedPostId) {
      toast.error('Vui lòng chọn bài viết');
      return;
    }
    
    setGenerating(true);
    try {
      const res = await generatePostImage(selectedPostId, { regenerate: true });
      toast.success('Đã gửi yêu cầu tạo ảnh...');
      startPolling(selectedPostId);
    } catch (err) {
      toast.error(err.message || 'Lỗi khi yêu cầu tạo ảnh');
      setGenerating(false);
    }
  };

  return (
    <div className="post-list-page" style={{ padding: '20px', maxWidth: '1200px', margin: '0 auto' }}>
      <div className="page-header" style={{ marginBottom: '30px' }}>
        <h2 style={{ fontSize: '2rem', fontWeight: 'bold', background: 'linear-gradient(90deg, #3b82f6, #8b5cf6)', WebkitBackgroundClip: 'text', WebkitTextFillColor: 'transparent' }}>
          🖼️ Image Studio
        </h2>
        <p style={{ color: '#aaa', marginTop: '10px' }}>Trung tâm quản lý và khởi tạo hình ảnh cho các chiến dịch Content của bạn.</p>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 2fr', gap: '30px' }}>
        {/* Left Column - Controls */}
        <div style={{ backgroundColor: '#1e1e1e', padding: '20px', borderRadius: '12px', border: '1px solid #333' }}>
          <h3 style={{ marginBottom: '20px', borderBottom: '1px solid #333', paddingBottom: '10px' }}>Cấu hình sinh ảnh</h3>
          
          <div className="form-group">
            <label style={{ display: 'block', marginBottom: '8px', color: '#ccc' }}>1. Chọn bài viết nguồn</label>
            <select 
              className="w-full" 
              style={{ padding: '10px', borderRadius: '8px', backgroundColor: '#2d2d2d', color: '#fff', border: '1px solid #444', width: '100%' }}
              value={selectedPostId} 
              onChange={handleSelectPost}
            >
              <option value="">-- Chọn bài viết --</option>
              {posts.map(p => (
                <option key={p.id} value={p.id}>{p.title || `Bài viết #${p.id}`}</option>
              ))}
            </select>
          </div>

          {selectedPost && (
            <div style={{ marginTop: '20px', padding: '15px', backgroundColor: 'rgba(59, 130, 246, 0.1)', borderRadius: '8px', borderLeft: '4px solid #3b82f6' }}>
              <p style={{ margin: 0, fontSize: '0.9rem', color: '#93c5fd' }}>
                Hệ thống sẽ đọc hiểu nội dung bài viết <strong>"{selectedPost.title}"</strong> để tự động trích xuất các ý tưởng hình ảnh phù hợp.
              </p>
            </div>
          )}

          <button 
            onClick={handleGenerate} 
            disabled={!selectedPostId || generating}
            style={{ 
              width: '100%', 
              marginTop: '30px', 
              padding: '15px', 
              borderRadius: '8px', 
              border: 'none',
              background: generating ? '#555' : 'linear-gradient(135deg, #3b82f6, #2563eb)',
              color: 'white',
              fontWeight: 'bold',
              fontSize: '1.1rem',
              cursor: (!selectedPostId || generating) ? 'not-allowed' : 'pointer',
              transition: 'all 0.3s',
              boxShadow: generating ? 'none' : '0 4px 15px rgba(59, 130, 246, 0.4)'
            }}
          >
            {generating ? '⏳ Đang khởi tạo...' : '✨ Tạo Hình Ảnh Mới'}
          </button>
        </div>

        {/* Right Column - Preview */}
        <div style={{ backgroundColor: '#1e1e1e', padding: '20px', borderRadius: '12px', border: '1px solid #333', minHeight: '500px', display: 'flex', flexDirection: 'column' }}>
          <h3 style={{ marginBottom: '20px', borderBottom: '1px solid #333', paddingBottom: '10px' }}>Kết quả</h3>
          
          <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center', backgroundColor: '#121212', borderRadius: '8px', border: '1px dashed #444', overflow: 'hidden' }}>
            {!selectedPost ? (
              <p style={{ color: '#666' }}>Vui lòng chọn bài viết để xem hoặc tạo ảnh.</p>
            ) : generating ? (
              <div style={{ textAlign: 'center' }}>
                <div style={{ 
                  display: 'inline-block', 
                  width: '50px', 
                  height: '50px', 
                  border: '3px solid rgba(59, 130, 246, 0.3)', 
                  borderRadius: '50%', 
                  borderTopColor: '#3b82f6', 
                  animation: 'spin 1s ease-in-out infinite' 
                }} />
                <p style={{ color: '#3b82f6', marginTop: '15px', fontWeight: '500' }}>AI đang vẽ ảnh, vui lòng chờ...</p>
                <style>
                  {`
                    @keyframes spin {
                      to { transform: rotate(360deg); }
                    }
                  `}
                </style>
              </div>
            ) : selectedPost.image_url ? (
              <div style={{ position: 'relative', width: '100%', height: '100%' }}>
                <img src={selectedPost.image_url} alt="Generated" style={{ width: '100%', height: '100%', objectFit: 'contain' }} />
              </div>
            ) : (
              <p style={{ color: '#666' }}>Chưa có hình ảnh nào cho bài viết này.</p>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};

export default ImageStudio;
