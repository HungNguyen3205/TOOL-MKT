import React, { useEffect, useState, useCallback } from 'react';
import { fetchPosts, deletePost, duplicatePost, updatePost, generatePostImage } from '../api/posts';
import { getConnectedPages } from '../api/facebook';
import { Link, useNavigate } from 'react-router-dom';

const PostList = () => {
  const [posts, setPosts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [meta, setMeta] = useState(null);
  
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('all');
  const [source, setSource] = useState('all');
  const [qualityStatus, setQualityStatus] = useState('all');
  const [page, setPage] = useState(1);
  
  const [showPublishModal, setShowPublishModal] = useState(false);
  const [publishPostId, setPublishPostId] = useState(null);
  const [facebookPages, setFacebookPages] = useState([]);
  const [selectedPageId, setSelectedPageId] = useState('');
  const [scheduleData, setScheduleData] = useState({ date: '', time: '' });
  const [publishing, setPublishing] = useState(false);
  const [generatingImages, setGeneratingImages] = useState({});

  useEffect(() => {
    loadFacebookPages();
  }, []);

  const loadFacebookPages = async () => {
    try {
      const res = await getConnectedPages();
      setFacebookPages(res.data || []);
    } catch (err) {
      console.error(err);
    }
  };
  
  const navigate = useNavigate();

  const statusMap = {
    draft: { label: 'Bản nháp', color: '#757575' },
    generating_content: { label: 'Đang tạo nội dung', color: '#2196f3' },
    generating_image: { label: 'Đang tạo ảnh', color: '#2196f3' },
    ready: { label: 'Sẵn sàng', color: '#4caf50' },
    scheduled: { label: 'Đã lên lịch', color: '#ff9800' },
    publishing: { label: 'Đang đăng', color: '#ff9800' },
    published: { label: 'Đã đăng', color: '#0d47a1' },
    failed: { label: 'Lỗi', color: '#f44336' },
    cancelled: { label: 'Đã hủy', color: '#757575' },
    // Legacy states
    in_review: { label: 'Chờ duyệt', color: '#2196f3' },
    changes_requested: { label: 'Cần chỉnh sửa', color: '#f44336' },
    approved: { label: 'Đã duyệt', color: '#9c27b0' }
  };

  const loadPosts = useCallback(async () => {
    setLoading(true);
    try {
      const params = { search, status, source, page };
      if (qualityStatus !== 'all') params.quality_status = qualityStatus;
      
      const data = await fetchPosts(params);
      setPosts(data.data);
      setMeta(data.meta);
    } catch (err) {
      console.error(err);
      alert('Không thể tải danh sách bài viết.');
    } finally {
      setLoading(false);
    }
  }, [search, status, source, qualityStatus, page]);

  // Debounce search
  useEffect(() => {
    const timer = setTimeout(() => {
      setPage(1); // Reset page on new search/filter
      loadPosts();
    }, 500);
    return () => clearTimeout(timer);
  }, [search, status, source, qualityStatus, loadPosts]);

  const handleDelete = async (id, title) => {
    if (window.confirm(`Bạn có chắc muốn xóa bài viết "${title}"?`)) {
      try {
        await deletePost(id);
        loadPosts();
      } catch (err) {
        console.error(err);
        alert('Lỗi khi xóa bài viết.');
      }
    }
  };

  const handleGenerateImage = async (postId) => {
    setGeneratingImages(prev => ({ ...prev, [postId]: true }));
    try {
      await generatePostImage(postId, { regenerate: true });
      setTimeout(() => {
        loadPosts();
      }, 3000);
    } catch (err) {
      alert('Lỗi tạo ảnh: ' + (err.message || err.toString()));
    } finally {
      setGeneratingImages(prev => ({ ...prev, [postId]: false }));
    }
  };

  const handleDuplicate = async (id) => {
    try {
      const res = await duplicatePost(id);
      if (res.success) {
        navigate(`/posts/${res.data.id}/edit`);
      }
    } catch (err) {
      alert('Lỗi khi nhân bản bài viết.');
    }
  };

  const handlePublishOrSchedule = async (action) => {
    if (!selectedPageId) {
      alert('Vui lòng chọn Fanpage!');
      return;
    }
    
    setPublishing(true);
    try {
      if (action === 'schedule') {
        if (!scheduleData.date || !scheduleData.time) {
          alert('Vui lòng chọn Ngày và Giờ để lên lịch!');
          setPublishing(false);
          return;
        }
        
        // For scheduling, we update the post status and scheduled_at
        const payload = { 
          facebook_page_id: selectedPageId,
          status: 'scheduled',
          scheduled_at: `${scheduleData.date}T${scheduleData.time}:00`
        };
        await updatePost(publishPostId, payload);
        alert('Đã lên lịch thành công!');
      } else {
        // For immediate publish, we call the specific publish API (like PostPublish.jsx does)
        const payload = {
          facebook_page_id: selectedPageId,
          confirmation: true // auto-confirm since they clicked Publish
        };
        const { publishPost } = await import('../api/facebook');
        await publishPost(publishPostId, payload);
        
        // Cập nhật giao diện mượn tạm trạng thái
        await updatePost(publishPostId, { status: 'publishing' }); 
        alert('Đã đưa vào hàng đợi đăng!');
      }
      
      setShowPublishModal(false);
      setPublishPostId(null);
      loadPosts();
    } catch (err) {
      alert('Có lỗi xảy ra: ' + (err.message || err.toString()));
    } finally {
      setPublishing(false);
    }
  };

  return (
    <div className="post-list-page">
      {/* PUBLISH MODAL */}
      {showPublishModal && (
        <div style={{ position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, backgroundColor: 'rgba(0,0,0,0.8)', zIndex: 1000, display: 'flex', justifyContent: 'center', alignItems: 'center', padding: 20 }}>
          <div style={{ backgroundColor: '#222', width: '100%', maxWidth: '500px', borderRadius: 8, padding: 20 }}>
            <h3 style={{ marginTop: 0, color: '#fff' }}>Đăng hoặc Lên lịch bài viết</h3>
            
            <div className="form-group" style={{ marginBottom: '20px' }}>
              <label style={{ color: '#ccc' }}>📍 Chọn Fanpage</label>
              <select 
                value={selectedPageId} 
                onChange={e => setSelectedPageId(e.target.value)}
                style={{ width: '100%', padding: '10px', marginTop: '5px', borderRadius: '4px', border: '1px solid #444', backgroundColor: '#333', color: '#fff' }}
              >
                <option value="">-- Chọn Fanpage --</option>
                {facebookPages.map(page => (
                  <option key={page.id} value={page.id}>{page.page_name}</option>
                ))}
              </select>
            </div>

            <div style={{ padding: '15px', backgroundColor: '#333', borderRadius: '8px', marginBottom: '20px', border: '1px solid #444' }}>
              <strong style={{ color: '#ff9800', display: 'block', marginBottom: '10px' }}>🕒 Tùy chọn Lên lịch (Bỏ trống nếu muốn đăng ngay)</strong>
              <div style={{ display: 'flex', gap: '10px' }}>
                <input type="date" value={scheduleData.date} onChange={e => setScheduleData({...scheduleData, date: e.target.value})} style={{ flex: 1, padding: '8px', borderRadius: '4px', border: '1px solid #444', backgroundColor: '#222', color: '#fff' }} />
                <input type="time" value={scheduleData.time} onChange={e => setScheduleData({...scheduleData, time: e.target.value})} style={{ flex: 1, padding: '8px', borderRadius: '4px', border: '1px solid #444', backgroundColor: '#222', color: '#fff' }} />
              </div>
            </div>

            <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end', marginTop: 20 }}>
              <button className="btn-secondary" onClick={() => setShowPublishModal(false)} disabled={publishing}>Hủy</button>
              <button 
                className="btn-primary" 
                style={{ backgroundColor: '#ff9800' }} 
                onClick={() => handlePublishOrSchedule('schedule')}
                disabled={publishing}
              >
                Lên lịch
              </button>
              <button 
                className="btn-success" 
                onClick={() => handlePublishOrSchedule('publish')}
                disabled={publishing}
              >
                Đăng ngay
              </button>
            </div>
          </div>
        </div>
      )}

      <div className="page-header">
        <h2>Danh sách bài viết</h2>
        <Link to="/posts/new" className="btn-primary">Tạo bài viết thủ công</Link>
      </div>

      <div className="filters-container">
        <div className="search-box">
          <span className="search-icon">🔍</span>
          <input 
            type="text" 
            placeholder="Tìm kiếm tiêu đề..." 
            value={search} 
            onChange={(e) => setSearch(e.target.value)} 
            className="search-input"
          />
        </div>
        
        <div className="select-group">
          <select value={status} onChange={(e) => setStatus(e.target.value)} className="custom-select">
            <option value="all">Tất cả trạng thái</option>
            <option value="draft">Bản nháp</option>
            <option value="in_review">Chờ duyệt</option>
            <option value="changes_requested">Cần chỉnh sửa</option>
            <option value="approved">Đã duyệt</option>
            <option value="ready">Sẵn sàng đăng</option>
          </select>
          
          <select value={qualityStatus} onChange={(e) => setQualityStatus(e.target.value)} className="custom-select">
            <option value="all">Mọi chất lượng</option>
            <option value="passed">Đạt yêu cầu</option>
            <option value="warning">Cảnh báo</option>
            <option value="failed">Không đạt</option>
            <option value="unchecked">Chưa kiểm tra</option>
          </select>
          
          <select value={source} onChange={(e) => setSource(e.target.value)} className="custom-select">
            <option value="all">Tất cả nguồn</option>
            <option value="manual">Thủ công</option>
            <option value="ai_generated">AI tạo</option>
            <option value="ai_edited">AI sửa</option>
          </select>
        </div>
      </div>

      <div className="post-table-container">
        <table className="post-table">
          <thead>
            <tr>
              <th style={{ width: '120px' }}>Hình ảnh</th>
              <th>Tiêu đề & Nội dung</th>
              <th>Thông tin</th>
              <th>Trạng thái</th>
              <th style={{ width: '150px', textAlign: 'center' }}>Hành động</th>
            </tr>
          </thead>
          <tbody>
            {loading ? (
              <tr>
                <td colSpan="5" className="loading" style={{ textAlign: 'center', padding: '40px' }}>Đang tải danh sách bài viết...</td>
              </tr>
            ) : posts.length === 0 ? (
              <tr>
                <td colSpan="5" className="empty-state" style={{ textAlign: 'center', padding: '40px' }}>Không tìm thấy bài viết nào.</td>
              </tr>
            ) : (
              posts.map(post => (
                <tr key={post.id}>
                  <td>
                    {post.final_image_path || post.image_path ? (
                      <div style={{ 
                        width: '100px', height: '100px', 
                        backgroundImage: `url(http://localhost:8000/storage/${post.final_image_path || post.image_path})`, 
                        backgroundSize: 'cover', backgroundPosition: 'center', 
                        borderRadius: '8px', border: '1px solid var(--border)' 
                      }}></div>
                    ) : (
                      <div className="img-placeholder">
                        {generatingImages[post.id] || post.status === 'generating_image' ? (
                          <>
                            <div className="spinner"></div>
                            <span style={{ fontSize: '0.7rem', color: 'var(--primary)' }}>Đang tạo...</span>
                          </>
                        ) : (
                          <button 
                            className="btn-secondary" 
                            style={{ padding: '6px 12px', fontSize: '0.75rem', display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '4px' }}
                            onClick={() => handleGenerateImage(post.id)}
                            disabled={generatingImages[post.id]}
                          >
                            <span style={{ fontSize: '1.2rem' }}>✨</span>
                            Tạo ảnh
                          </button>
                        )}
                      </div>
                    )}
                  </td>
                  <td>
                    <h4 style={{ margin: '0 0 8px 0', color: '#fff', fontSize: '1rem' }}>{post.title}</h4>
                    <p style={{ margin: 0, color: 'var(--text-muted)', fontSize: '0.85rem', lineHeight: '1.4', display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', overflow: 'hidden' }}>
                      {post.content ? post.content : 'Chưa có nội dung'}
                    </p>
                  </td>
                  <td style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>
                    <div style={{ marginBottom: '4px' }}><strong style={{ color: '#ccc' }}>Fanpage:</strong> {post.facebook_page_id || 'Chưa chọn'}</div>
                    <div><strong style={{ color: '#ccc' }}>Ngày đăng:</strong> {post.scheduled_at ? new Date(post.scheduled_at).toLocaleString('vi-VN') : (post.published_at ? new Date(post.published_at).toLocaleString('vi-VN') : 'Chưa thiết lập')}</div>
                  </td>
                  <td>
                    <span style={{ 
                      backgroundColor: statusMap[post.status]?.color || '#757575', 
                      color: '#fff', padding: '4px 10px', borderRadius: '4px', fontSize: '0.8rem', fontWeight: 'bold', display: 'inline-block' 
                    }}>
                      {statusMap[post.status]?.label || post.status}
                    </span>
                  </td>
                  <td>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '6px' }}>
                      <Link 
                        to={`/posts/${post.id}/edit`} 
                        style={{ padding: '6px', backgroundColor: 'rgba(59, 130, 246, 0.1)', color: '#60a5fa', borderRadius: '4px', textDecoration: 'none', fontWeight: '500', textAlign: 'center', fontSize: '0.8rem' }}
                      >
                        Chỉnh sửa
                      </Link>
                      
                      {['draft', 'ready', 'published', 'failed', 'image_failed', 'scheduled', 'publishing'].includes(post.status) && (
                        <button 
                          onClick={() => { setPublishPostId(post.id); setShowPublishModal(true); }}
                          style={{ padding: '6px', backgroundColor: 'rgba(16, 185, 129, 0.1)', color: '#34d399', borderRadius: '4px', border: 'none', fontWeight: '500', cursor: 'pointer', fontSize: '0.8rem' }}
                        >
                          Đăng bài
                        </button>
                      )}
                      
                      {post.status === 'scheduled' && (
                        <button 
                          onClick={async () => {
                            try {
                               await updatePost(post.id, { status: 'draft' });
                               loadPosts();
                            } catch(e){}
                          }}
                          style={{ padding: '6px', backgroundColor: 'rgba(239, 68, 68, 0.1)', color: '#f87171', borderRadius: '4px', border: 'none', fontWeight: '500', cursor: 'pointer', fontSize: '0.8rem' }}
                        >
                          Hủy lịch
                        </button>
                      )}
                      
                      <button 
                        onClick={() => handleDelete(post.id, post.title)} 
                        style={{ padding: '6px', backgroundColor: 'transparent', color: 'var(--text-muted)', borderRadius: '4px', border: '1px solid rgba(255,255,255,0.1)', fontWeight: '500', cursor: 'pointer', fontSize: '0.8rem' }}
                        onMouseEnter={(e) => { e.target.style.color = '#f87171'; e.target.style.borderColor = '#f87171'; }}
                        onMouseLeave={(e) => { e.target.style.color = 'var(--text-muted)'; e.target.style.borderColor = 'rgba(255,255,255,0.1)'; }}
                      >
                        Xóa
                      </button>
                    </div>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {meta && meta.last_page > 1 && (
        <div className="pagination">
          <button 
            disabled={page === 1} 
            onClick={() => setPage(p => p - 1)}
          >
            Trang trước
          </button>
          <span>Trang {page} / {meta.last_page}</span>
          <button 
            disabled={page === meta.last_page} 
            onClick={() => setPage(p => p + 1)}
          >
            Trang sau
          </button>
        </div>
      )}
    </div>
  );
};

export default PostList;
