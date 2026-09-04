import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { fetchPost } from '../api/posts';
import { getConnectedPages, publishPost } from '../api/facebook';

const PostPublish = () => {
  const { id } = useParams();
  const navigate = useNavigate();

  const [post, setPost] = useState(null);
  const [pages, setPages] = useState([]);
  const [selectedPageId, setSelectedPageId] = useState('');
  
  const [loading, setLoading] = useState(true);
  const [publishing, setPublishing] = useState(false);
  const [confirmed, setConfirmed] = useState(false);

  useEffect(() => {
    const loadData = async () => {
      try {
        const [postRes, pagesRes] = await Promise.all([
          fetchPost(id),
          getConnectedPages()
        ]);
        
        setPost(postRes.data);
        
        const activePages = pagesRes.data.filter(p => p.connection_status === 'connected');
        setPages(activePages);
        if (activePages.length > 0) {
          setSelectedPageId(activePages[0].id.toString());
        }
      } catch (err) {
        alert('Lỗi khi tải dữ liệu bài viết hoặc danh sách Page.');
      } finally {
        setLoading(false);
      }
    };
    
    loadData();
  }, [id]);

  const buildPreview = () => {
    if (!post) return '';
    const parts = [];
    if (post.title) parts.push(post.title);
    if (post.content) parts.push(post.content);
    if (post.cta) parts.push(post.cta);
    
    if (post.hashtags) {
      let tagsArray = [];
      if (Array.isArray(post.hashtags)) {
        tagsArray = post.hashtags;
      } else if (typeof post.hashtags === 'string' && post.hashtags.trim().length > 0) {
        tagsArray = post.hashtags.split(',').map(s => s.trim()).filter(s => s);
      }
      
      if (tagsArray.length > 0) {
        const tags = tagsArray.map(t => t.startsWith('#') ? t : `#${t}`);
        parts.push(tags.join(' '));
      }
    }
    return parts.join('\n\n');
  };

  const handlePublish = async () => {
    if (!selectedPageId) {
      alert('Vui lòng chọn Facebook Page.');
      return;
    }
    if (!confirmed) {
      alert('Vui lòng xác nhận trước khi đăng.');
      return;
    }

    setPublishing(true);
    try {
      const payload = {
          facebook_page_id: selectedPageId,
          confirmation: confirmed
      };
      await publishPost(post.id, payload);
      alert('Đã đưa bài viết vào hàng đợi đăng thành công!');
      navigate(`/posts/${post.id}/publications`);
    } catch (err) {
      alert(err.message || 'Có lỗi xảy ra khi yêu cầu đăng bài.');
      setPublishing(false);
    }
  };

  if (loading) return <div className="loading">Đang tải dữ liệu Đăng bài...</div>;
  if (!post) return <div className="error-alert">Không tìm thấy bài viết.</div>;

  return (
    <div className="publish-page" style={{ padding: '0 20px', maxWidth: '1200px', margin: '0 auto' }}>
      <div className="editor-header" style={{ marginBottom: 30 }}>
        <div className="header-left">
          <button className="btn-secondary" onClick={() => navigate(`/posts/${post.id}/edit`)} disabled={publishing}>
            &larr; Quay lại Trình soạn thảo
          </button>
          <h2>Đăng bài lên Facebook</h2>
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '40px' }}>
        {/* Cột trái: Cấu hình và Xác nhận */}
        <div className="publish-config">
          <div style={{ backgroundColor: '#1e1e1e', padding: '30px', borderRadius: '12px', border: '1px solid #333' }}>
            <h3 style={{ marginTop: 0, marginBottom: '20px', color: '#fff', fontSize: '1.5rem' }}>1. Chọn nơi đăng</h3>
            
            {pages.length === 0 ? (
              <div className="error-alert" style={{ backgroundColor: '#3e2723', border: '1px solid #f44336', color: '#f44336' }}>
                <strong>Lỗi:</strong> Bạn chưa có Facebook Page nào khả dụng hoặc token đã hết hạn.<br/><br/>
                Vui lòng vào menu <strong>Facebook Pages</strong> để thêm kết nối mới.
              </div>
            ) : (
              <div className="form-group" style={{ marginBottom: '30px' }}>
                <label style={{ color: '#aaa', fontSize: '0.9rem', marginBottom: '8px', display: 'block' }}>
                  Facebook Page của bạn:
                </label>
                <select 
                  value={selectedPageId} 
                  onChange={(e) => setSelectedPageId(e.target.value)} 
                  disabled={publishing}
                  style={{ 
                    width: '100%', 
                    padding: '15px', 
                    fontSize: '1.1rem', 
                    backgroundColor: '#2a2a2a', 
                    color: '#fff', 
                    border: '1px solid #444',
                    borderRadius: '8px',
                    cursor: 'pointer'
                  }}
                >
                  {pages.map(p => (
                    <option key={p.id} value={p.id}>{p.page_name}</option>
                  ))}
                </select>
              </div>
            )}

            <h3 style={{ marginTop: '40px', marginBottom: '20px', color: '#fff', fontSize: '1.5rem' }}>2. Xác nhận</h3>
            
            <div 
              onClick={() => !publishing && setConfirmed(!confirmed)}
              style={{ 
                display: 'flex', 
                alignItems: 'flex-start', 
                gap: '15px', 
                padding: '20px', 
                backgroundColor: confirmed ? 'rgba(76, 175, 80, 0.1)' : '#2a2a2a',
                border: `2px solid ${confirmed ? '#4caf50' : '#444'}`,
                borderRadius: '12px',
                cursor: publishing ? 'not-allowed' : 'pointer',
                transition: 'all 0.3s ease'
              }}
            >
              <input 
                type="checkbox" 
                checked={confirmed} 
                readOnly
                style={{ width: '24px', height: '24px', accentColor: '#4caf50', marginTop: '2px', cursor: 'pointer' }}
              />
              <label style={{ margin: 0, fontWeight: '500', color: confirmed ? '#4caf50' : '#ccc', cursor: 'pointer', lineHeight: '1.5' }}>
                Tôi đã kiểm tra kỹ nội dung và xác nhận bài viết này sẽ được phát hành công khai lên Facebook Page đã chọn.
              </label>
            </div>

            <div style={{ marginTop: '40px' }}>
              <button 
                onClick={handlePublish} 
                disabled={publishing || !confirmed || !selectedPageId}
                style={{
                  width: '100%',
                  padding: '18px 24px',
                  fontSize: '1.2rem',
                  fontWeight: 'bold',
                  color: '#fff',
                  backgroundColor: (publishing || !confirmed || !selectedPageId) ? '#444' : '#1877f2',
                  border: 'none',
                  borderRadius: '12px',
                  cursor: (publishing || !confirmed || !selectedPageId) ? 'not-allowed' : 'pointer',
                  boxShadow: (publishing || !confirmed || !selectedPageId) ? 'none' : '0 8px 16px rgba(24, 119, 242, 0.3)',
                  transition: 'all 0.3s ease',
                  textTransform: 'uppercase',
                  letterSpacing: '1px'
                }}
                onMouseOver={(e) => {
                  if (!publishing && confirmed && selectedPageId) {
                    e.currentTarget.style.transform = 'translateY(-2px)';
                    e.currentTarget.style.boxShadow = '0 12px 20px rgba(24, 119, 242, 0.4)';
                  }
                }}
                onMouseOut={(e) => {
                  if (!publishing && confirmed && selectedPageId) {
                    e.currentTarget.style.transform = 'translateY(0)';
                    e.currentTarget.style.boxShadow = '0 8px 16px rgba(24, 119, 242, 0.3)';
                  }
                }}
              >
                {publishing ? (
                  <span style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '10px' }}>
                    <span className="spinner" style={{ width: '20px', height: '20px', border: '3px solid rgba(255,255,255,0.3)', borderTopColor: '#fff', borderRadius: '50%', animation: 'spin 1s linear infinite' }}></span>
                    ĐANG XỬ LÝ...
                  </span>
                ) : (
                  '🚀 XÁC NHẬN ĐĂNG BÀI'
                )}
              </button>
              <style>{`
                @keyframes spin {
                  to { transform: rotate(360deg); }
                }
              `}</style>
            </div>
          </div>
        </div>

        {/* Cột phải: Xem trước */}
        <div className="publish-preview">
          <div style={{ backgroundColor: '#1e1e1e', padding: '30px', borderRadius: '12px', border: '1px solid #333' }}>
            <h3 style={{ marginTop: 0, marginBottom: '20px', color: '#fff', fontSize: '1.5rem' }}>Bản xem trước nội dung</h3>
            <div 
              style={{
                backgroundColor: '#111', 
                padding: '25px', 
                borderRadius: '8px', 
                whiteSpace: 'pre-wrap', 
                fontSize: '1rem',
                lineHeight: '1.6',
                color: '#e0e0e0',
                border: '1px solid #222',
                maxHeight: '600px',
                overflowY: 'auto'
              }}
            >
              {buildPreview()}
            </div>
            
            <div style={{ marginTop: '20px', padding: '15px', backgroundColor: 'rgba(255, 152, 0, 0.1)', borderLeft: '4px solid #ff9800', borderRadius: '4px' }}>
              <p style={{ margin: 0, color: '#ffb300', fontSize: '0.9rem' }}>
                <strong style={{display:'block', marginBottom:'5px'}}>💡 Lưu ý quan trọng:</strong> 
                Nội dung hiển thị ở trên là đoạn Text nguyên bản sẽ được gửi qua Facebook API. Định dạng và khoảng cách dòng có thể thay đổi một chút khi hiển thị thực tế trên Facebook App tùy thuộc vào thiết bị của người xem.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default PostPublish;
