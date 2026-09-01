import React, { useEffect, useState, useCallback } from 'react';
import { fetchPosts, deletePost, duplicatePost, changePostStatus } from '../api/posts';
import { Link, useNavigate } from 'react-router-dom';

const PostList = () => {
  const [posts, setPosts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [meta, setMeta] = useState(null);
  
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('all');
  const [source, setSource] = useState('all');
  const [page, setPage] = useState(1);
  
  const navigate = useNavigate();

  const loadPosts = useCallback(async () => {
    setLoading(true);
    try {
      const data = await fetchPosts({ search, status, source, page });
      setPosts(data.data);
      setMeta(data.meta);
    } catch (err) {
      console.error(err);
      alert('Không thể tải danh sách bài viết.');
    } finally {
      setLoading(false);
    }
  }, [search, status, source, page]);

  // Debounce search
  useEffect(() => {
    const timer = setTimeout(() => {
      setPage(1); // Reset page on new search/filter
      loadPosts();
    }, 500);
    return () => clearTimeout(timer);
  }, [search, status, source, loadPosts]);

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
          <option value="ready">Sẵn sàng</option>
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
                {post.content.substring(0, 100)}...
              </p>
              <div className="post-meta">
                <span className={`status badge-${post.status}`}>{post.status === 'ready' ? 'Sẵn sàng' : 'Bản nháp'}</span>
                <span className="source badge-source">{post.source}</span>
              </div>
              <div className="post-actions">
                <Link to={`/posts/${post.id}/edit`} className="btn-secondary">Sửa</Link>
                <button onClick={() => handleDuplicate(post.id)} className="btn-secondary">Nhân bản</button>
                <button onClick={() => handleDelete(post.id, post.title)} className="btn-danger">Xóa</button>
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
