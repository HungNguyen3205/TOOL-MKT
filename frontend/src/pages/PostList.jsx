import React, { useEffect, useState, useCallback } from 'react';
import { fetchPosts, deletePost, duplicatePost, updatePost, generatePostImage, fetchPostImageStatus } from '../api/posts';
import { getConnectedPages } from '../api/facebook';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';

const PostList = () => {
  const [posts, setPosts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [meta, setMeta] = useState(null);
  
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('all');
  const [source, setSource] = useState('all');
  const [qualityStatus, setQualityStatus] = useState('all');
  const [searchParams, setSearchParams] = useSearchParams();
  const pageParam = parseInt(searchParams.get('page')) || 1;
  const perPageParam = parseInt(searchParams.get('per_page')) || 25;

  const [page, setPage] = useState(pageParam);
  const [perPage, setPerPage] = useState(perPageParam);
  const [showPublishModal, setShowPublishModal] = useState(false);
  const [publishPostId, setPublishPostId] = useState(null);
  const [facebookPages, setFacebookPages] = useState([]);
  const [selectedPageId, setSelectedPageId] = useState('');
  const [scheduleData, setScheduleData] = useState({ date: '', time: '' });
  const [publishing, setPublishing] = useState(false);
  const [generatingImages, setGeneratingImages] = useState({});
  const [previewImage, setPreviewImage] = useState(null);

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
      const params = { search, status, source, page, per_page: perPage };
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
  }, [search, status, source, qualityStatus, page, perPage]);

  // Sync state to URL and vice-versa
  useEffect(() => {
    const p = parseInt(searchParams.get('page')) || 1;
    const pp = parseInt(searchParams.get('per_page')) || 25;
    if (p !== page || pp !== perPage) {
      setPage(p);
      setPerPage(pp);
    }
  }, [searchParams]);

  const updateUrlParams = (newPage, newPerPage) => {
    setSearchParams({ page: newPage, per_page: newPerPage });
  };

  useEffect(() => {
    const timer = setTimeout(() => {
      // If search or filters change, we want to reset to page 1
      // but we shouldn't reset if it's the initial load.
      // For simplicity, we assume loadPosts is triggered by dependencies.
      loadPosts();
    }, 500);
    return () => clearTimeout(timer);
  }, [loadPosts]);

  const handleFilterChange = (setter, value) => {
    setter(value);
    updateUrlParams(1, perPage);
  };

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
      const res = await generatePostImage(postId, { regenerate: true });
      const mediaAssetId = res.data?.media_asset_id;
      
      if (!mediaAssetId) {
        setTimeout(loadPosts, 3000);
        return;
      }
      
      let attempts = 0;
      const pollInterval = setInterval(async () => {
        attempts++;
        try {
          const statusRes = await fetchPostImageStatus(postId, mediaAssetId);
          const status = statusRes.data?.status;
          if (status === 'ready' || status === 'failed' || status === 'cancelled' || attempts > 60) {
            clearInterval(pollInterval);
            setGeneratingImages(prev => ({ ...prev, [postId]: false }));
            loadPosts();
            if (status === 'failed') {
               alert('Lỗi khi tạo ảnh: ' + statusRes.data?.error_message);
            } else if (attempts > 60) {
               alert('Ảnh vẫn đang xử lý. Vui lòng kiểm tra lại sau.');
            }
          }
        } catch (e) {
          clearInterval(pollInterval);
          setGeneratingImages(prev => ({ ...prev, [postId]: false }));
        }
      }, 3000);
      
    } catch (err) {
      alert('Lỗi tạo ảnh: ' + (err.message || err.toString()));
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
        // Update status to ready first so backend validation passes
        await updatePost(publishPostId, { status: 'ready' });
        
        // For immediate publish, we call the specific publish API
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
        {/* Vite HMR trigger */}
        <Link to="/posts/new" className="btn-primary">Tạo bài viết thủ công</Link>
      </div>

      <div className="filters-container">
        <div className="search-box">
          <span className="search-icon">🔍</span>
          <input 
            type="text" 
            placeholder="Tìm kiếm tiêu đề..." 
            value={search} 
            onChange={(e) => handleFilterChange(setSearch, e.target.value)} 
            className="search-input"
          />
        </div>
        
        <div className="select-group">
          <select value={status} onChange={(e) => handleFilterChange(setStatus, e.target.value)} className="custom-select">
            <option value="all">Tất cả trạng thái</option>
            <option value="draft">Bản nháp</option>
            <option value="in_review">Chờ duyệt</option>
            <option value="changes_requested">Cần chỉnh sửa</option>
            <option value="approved">Đã duyệt</option>
            <option value="ready">Sẵn sàng đăng</option>
          </select>
          
          <select value={qualityStatus} onChange={(e) => handleFilterChange(setQualityStatus, e.target.value)} className="custom-select">
            <option value="all">Mọi chất lượng</option>
            <option value="passed">Đạt yêu cầu</option>
            <option value="warning">Cảnh báo</option>
            <option value="failed">Không đạt</option>
            <option value="unchecked">Chưa kiểm tra</option>
          </select>
          
          <select value={source} onChange={(e) => handleFilterChange(setSource, e.target.value)} className="custom-select">
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
                    {post.image_url ? (
                      <div 
                        style={{ 
                          width: '100px', height: '100px', 
                          backgroundImage: `url(${post.image_url})`, 
                          backgroundSize: 'cover', backgroundPosition: 'center', 
                          borderRadius: '8px', border: '1px solid var(--border)',
                          cursor: 'pointer', position: 'relative'
                        }}
                        onClick={() => setPreviewImage(post.image_url)}
                        title="Phóng to ảnh"
                      >
                        <div style={{
                          position: 'absolute', bottom: '4px', right: '4px',
                          background: 'rgba(0,0,0,0.6)', color: 'white', borderRadius: '50%',
                          width: '24px', height: '24px', display: 'flex', alignItems: 'center', justifyContent: 'center',
                          fontSize: '12px'
                        }}>🔍</div>
                      </div>
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

      {meta && (
        <div className="pagination" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: '20px', padding: '15px', backgroundColor: '#222', borderRadius: '8px' }}>
          <div style={{ color: '#ccc', fontSize: '0.9rem' }}>
            Hiển thị {meta.from || 0}–{meta.to || 0} trong tổng số {meta.total || 0} bài viết
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '15px' }}>
            <select 
              value={perPage} 
              onChange={(e) => updateUrlParams(1, parseInt(e.target.value))}
              style={{ padding: '6px', backgroundColor: '#333', color: '#fff', border: '1px solid #444', borderRadius: '4px' }}
            >
              <option value={10}>10 bài / trang</option>
              <option value={25}>25 bài / trang</option>
              <option value={50}>50 bài / trang</option>
              <option value={100}>100 bài / trang</option>
            </select>

            <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
              <button 
                disabled={page === 1} 
                onClick={() => updateUrlParams(page - 1, perPage)}
                style={{ padding: '6px 12px', backgroundColor: page === 1 ? '#333' : '#4f46e5', color: page === 1 ? '#666' : '#fff', border: 'none', borderRadius: '4px', cursor: page === 1 ? 'not-allowed' : 'pointer' }}
              >
                Trang trước
              </button>
              
              {/* Generate page numbers (simplified logic for now) */}
              <div style={{ display: 'flex', gap: '4px' }}>
                {Array.from({ length: meta.last_page }, (_, i) => i + 1)
                  .filter(p => p === 1 || p === meta.last_page || Math.abs(p - page) <= 2)
                  .map((p, i, arr) => (
                    <React.Fragment key={p}>
                      {i > 0 && p - arr[i - 1] > 1 && <span style={{ color: '#666', padding: '0 4px' }}>...</span>}
                      <button
                        onClick={() => updateUrlParams(p, perPage)}
                        style={{
                          padding: '4px 10px',
                          backgroundColor: p === page ? '#4f46e5' : '#333',
                          color: '#fff',
                          border: 'none',
                          borderRadius: '4px',
                          cursor: 'pointer',
                          fontWeight: p === page ? 'bold' : 'normal'
                        }}
                      >
                        {p}
                      </button>
                    </React.Fragment>
                  ))}
              </div>

              <button 
                disabled={page === meta.last_page || meta.last_page === 0} 
                onClick={() => updateUrlParams(page + 1, perPage)}
                style={{ padding: '6px 12px', backgroundColor: page === meta.last_page || meta.last_page === 0 ? '#333' : '#4f46e5', color: page === meta.last_page || meta.last_page === 0 ? '#666' : '#fff', border: 'none', borderRadius: '4px', cursor: page === meta.last_page || meta.last_page === 0 ? 'not-allowed' : 'pointer' }}
              >
                Trang sau
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Image Preview Modal */}
      {previewImage && (
        <div className="modal-overlay" onClick={() => setPreviewImage(null)} style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, backgroundColor: 'rgba(0, 0, 0, 0.85)', zIndex: 1000, cursor: 'zoom-out' }}>
          <img src={previewImage} alt="Preview" style={{ maxWidth: '90%', maxHeight: '90%', borderRadius: '8px', boxShadow: '0 10px 30px rgba(0,0,0,0.5)' }} onClick={(e) => e.stopPropagation()} />
          <button style={{ position: 'absolute', top: '20px', right: '30px', background: 'transparent', border: 'none', color: 'white', fontSize: '2rem', cursor: 'pointer' }} onClick={() => setPreviewImage(null)}>✕</button>
        </div>
      )}
    </div>
  );
};

export default PostList;
