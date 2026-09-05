import React, { useEffect, useState, useCallback } from 'react';
import { fetchPosts, deletePost, duplicatePost } from '../api/posts';
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
    if (window.confirm(`Bạn có chắc chắn muốn xóa bài viết "${title}" không?`)) {
      try {
        await deletePost(id);
        loadPosts();
      } catch (err) {
        alert('Lỗi khi xóa bài viết.');
      }
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

  return (
    <div className="post-list-page">
      <div className="page-header">
        <h2>Danh sách bài viết</h2>
        <Link to="/posts/new" className="btn-primary">Tạo bài viết thủ công</Link>
      </div>

      <div className="filters">
        <input 
          type="text" 
          placeholder="Tìm kiếm tiêu đề..." 
          value={search} 
          onChange={(e) => setSearch(e.target.value)} 
          className="search-input"
        />
        
        <select value={status} onChange={(e) => setStatus(e.target.value)}>
          <option value="all">Tất cả trạng thái</option>
          <option value="draft">Bản nháp</option>
          <option value="in_review">Chờ duyệt</option>
          <option value="changes_requested">Cần chỉnh sửa</option>
          <option value="approved">Đã duyệt</option>
          <option value="ready">Sẵn sàng đăng</option>
        </select>
        
        <select value={qualityStatus} onChange={(e) => setQualityStatus(e.target.value)}>
          <option value="all">Mọi chất lượng</option>
          <option value="passed">Đạt yêu cầu</option>
          <option value="warning">Cảnh báo</option>
          <option value="failed">Không đạt</option>
          <option value="unchecked">Chưa kiểm tra</option>
        </select>
        
        <select value={source} onChange={(e) => setSource(e.target.value)}>
          <option value="all">Tất cả nguồn</option>
          <option value="manual">Viết thủ công</option>
          <option value="ai_generated">AI tạo</option>
          <option value="ai_edited">AI tạo & Sửa</option>
        </select>
      </div>

      <div className="post-grid">
        {loading ? (
          <div className="loading">Đang tải danh sách bài viết...</div>
        ) : posts.length === 0 ? (
          <div className="empty-state">Không tìm thấy bài viết nào.</div>
        ) : (
          posts.map(post => (
            <div key={post.id} className="post-card" style={{ display: 'flex', flexDirection: 'column' }}>
              {post.final_image_path ? (
                <div style={{ height: '200px', backgroundImage: `url(http://localhost:8000/storage/${post.final_image_path})`, backgroundSize: 'cover', backgroundPosition: 'center', borderRadius: '8px 8px 0 0', margin: '-20px -20px 15px -20px' }}></div>
              ) : post.image_path ? (
                <div style={{ height: '200px', backgroundImage: `url(http://localhost:8000/storage/${post.image_path})`, backgroundSize: 'cover', backgroundPosition: 'center', borderRadius: '8px 8px 0 0', margin: '-20px -20px 15px -20px' }}></div>
              ) : (
                <div style={{ height: '200px', backgroundColor: '#f0f0f0', display: 'flex', alignItems: 'center', justifyContent: 'center', borderRadius: '8px 8px 0 0', margin: '-20px -20px 15px -20px', color: '#888' }}>Chưa có ảnh</div>
              )}
              <h4>{post.title}</h4>
              <p className="excerpt">
                <strong>Fanpage:</strong> {post.facebook_page_id || 'Chưa chọn'}<br/>
                <strong>Ngày đăng:</strong> {post.scheduled_at ? new Date(post.scheduled_at).toLocaleString('vi-VN') : (post.published_at ? new Date(post.published_at).toLocaleString('vi-VN') : 'Chưa thiết lập')}<br/>
                {post.content ? post.content.substring(0, 60) + '...' : 'Không có nội dung'}
              </p>
              <div className="post-meta" style={{ display: 'flex', flexDirection: 'column', gap: '5px', marginBottom: '15px' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                  <span style={{ backgroundColor: statusMap[post.status]?.color || '#757575', color: '#fff', padding: '4px 10px', borderRadius: '4px', fontSize: '0.8rem', fontWeight: 'bold' }}>
                    {statusMap[post.status]?.label || post.status}
                  </span>
                </div>
              </div>
              
              <div style={{ display: 'flex', gap: '10px', marginTop: 'auto', flexWrap: 'wrap' }}>
                <Link 
                  to={`/posts/${post.id}/edit`} 
                  style={{ flex: '1 1 45%', textAlign: 'center', padding: '8px', backgroundColor: '#e3f2fd', color: '#1976d2', borderRadius: '6px', textDecoration: 'none', fontWeight: '500' }}
                >
                  Chỉnh sửa
                </Link>
                {post.status === 'ready' && (
                  <>
                    <button style={{ flex: '1 1 45%', padding: '8px', backgroundColor: '#fff3e0', color: '#f57c00', borderRadius: '6px', border: 'none', fontWeight: '500', cursor: 'pointer' }}>Lên lịch</button>
                    <button style={{ flex: '1 1 45%', padding: '8px', backgroundColor: '#e8f5e9', color: '#388e3c', borderRadius: '6px', border: 'none', fontWeight: '500', cursor: 'pointer' }}>Đăng ngay</button>
                  </>
                )}
                {post.status === 'scheduled' && (
                  <button style={{ flex: '1 1 45%', padding: '8px', backgroundColor: '#ffebee', color: '#d32f2f', borderRadius: '6px', border: 'none', fontWeight: '500', cursor: 'pointer' }}>Hủy lịch</button>
                )}
                <button 
                  onClick={() => handleDelete(post.id, post.title)} 
                  style={{ flex: '1 1 45%', padding: '8px', backgroundColor: '#fafafa', color: '#d32f2f', borderRadius: '6px', border: '1px solid #ffcdd2', fontWeight: '500', cursor: 'pointer' }}
                >
                  Xóa
                </button>
              </div>
            </div>
          ))
        )}
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
