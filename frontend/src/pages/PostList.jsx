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
    in_review: { label: 'Chờ duyệt', color: '#2196f3' },
    changes_requested: { label: 'Cần chỉnh sửa', color: '#f44336' },
    approved: { label: 'Đã duyệt', color: '#9c27b0' },
    ready: { label: 'Sẵn sàng đăng', color: '#4caf50' }
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
            <div key={post.id} className="post-card">
              <h4>{post.title}</h4>
              <p className="excerpt">
                <strong>Thương hiệu:</strong> {post.brand ? post.brand.name : 'Không có'}<br/>
                {post.content.substring(0, 80)}...
              </p>
              <div className="post-meta" style={{ display: 'flex', flexDirection: 'column', gap: '5px', marginBottom: '15px' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                  <span style={{ backgroundColor: statusMap[post.status]?.color || '#757575', color: '#fff', padding: '2px 8px', borderRadius: '4px', fontSize: '0.8rem' }}>
                    {statusMap[post.status]?.label || post.status}
                  </span>
                  <span style={{ fontSize: '0.85rem', color: '#666' }}>
                    Phiên bản: {post.content_version || 1}
                  </span>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.85rem' }}>
                  <span>Chất lượng: {post.quality_score !== null ? `${post.quality_score}/100` : 'Chưa kiểm tra'}</span>
                  <span style={{ color: '#888' }}>{new Date(post.updated_at).toLocaleDateString('vi-VN')}</span>
                </div>
              </div>
              <div style={{ display: 'flex', gap: '10px', marginTop: '15px' }}>
                <Link 
                  to={`/posts/${post.id}/edit`} 
                  style={{ 
                    flex: 1, textAlign: 'center', padding: '10px', backgroundColor: 'rgba(33, 150, 243, 0.1)', 
                    color: '#2196f3', borderRadius: '8px', textDecoration: 'none', fontWeight: '500',
                    transition: 'all 0.2s', border: '1px solid rgba(33, 150, 243, 0.3)'
                  }}
                  onMouseOver={(e) => { e.currentTarget.style.backgroundColor = 'rgba(33, 150, 243, 0.2)'; e.currentTarget.style.transform = 'translateY(-2px)' }}
                  onMouseOut={(e) => { e.currentTarget.style.backgroundColor = 'rgba(33, 150, 243, 0.1)'; e.currentTarget.style.transform = 'translateY(0)' }}
                >
                  Sửa
                </Link>
                <button 
                  onClick={() => handleDuplicate(post.id)} 
                  style={{ 
                    flex: 1, padding: '10px', backgroundColor: 'rgba(255, 152, 0, 0.1)', 
                    color: '#ff9800', borderRadius: '8px', border: '1px solid rgba(255, 152, 0, 0.3)', 
                    fontWeight: '500', cursor: 'pointer', transition: 'all 0.2s'
                  }}
                  onMouseOver={(e) => { e.currentTarget.style.backgroundColor = 'rgba(255, 152, 0, 0.2)'; e.currentTarget.style.transform = 'translateY(-2px)' }}
                  onMouseOut={(e) => { e.currentTarget.style.backgroundColor = 'rgba(255, 152, 0, 0.1)'; e.currentTarget.style.transform = 'translateY(0)' }}
                >
                  Nhân bản
                </button>
                <button 
                  onClick={() => handleDelete(post.id, post.title)} 
                  style={{ 
                    flex: 1, padding: '10px', backgroundColor: 'rgba(244, 67, 54, 0.1)', 
                    color: '#f44336', borderRadius: '8px', border: '1px solid rgba(244, 67, 54, 0.3)', 
                    fontWeight: '500', cursor: 'pointer', transition: 'all 0.2s'
                  }}
                  onMouseOver={(e) => { e.currentTarget.style.backgroundColor = 'rgba(244, 67, 54, 0.2)'; e.currentTarget.style.transform = 'translateY(-2px)' }}
                  onMouseOut={(e) => { e.currentTarget.style.backgroundColor = 'rgba(244, 67, 54, 0.1)'; e.currentTarget.style.transform = 'translateY(0)' }}
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
