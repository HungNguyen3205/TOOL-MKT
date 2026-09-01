import React, { useEffect, useState, useCallback } from 'react';
import { fetchBrands, deleteBrand, setDefaultBrand, toggleBrandStatus } from '../api/brands';
import { Link, useNavigate } from 'react-router-dom';

const BrandList = () => {
  const [brands, setBrands] = useState([]);
  const [loading, setLoading] = useState(true);
  const [meta, setMeta] = useState(null);
  
  const [search, setSearch] = useState('');
  const [isActive, setIsActive] = useState('all');
  const [page, setPage] = useState(1);
  
  const navigate = useNavigate();

  const loadBrands = useCallback(async () => {
    setLoading(true);
    try {
      const params = { page };
      if (search) params.search = search;
      if (isActive !== 'all') params.is_active = isActive;
      
      const data = await fetchBrands(params);
      setBrands(data.data);
      setMeta(data.meta);
    } catch (err) {
      console.error(err);
      alert('Không thể tải danh sách thương hiệu.');
    } finally {
      setLoading(false);
    }
  }, [search, isActive, page]);

  useEffect(() => {
    const timer = setTimeout(() => {
      setPage(1);
      loadBrands();
    }, 500);
    return () => clearTimeout(timer);
  }, [search, isActive, loadBrands]);

  const handleDelete = async (id, name) => {
    if (window.confirm(`Bạn có chắc chắn muốn xóa thương hiệu "${name}" không?`)) {
      try {
        await deleteBrand(id);
        loadBrands();
      } catch (err) {
        alert('Lỗi khi xóa thương hiệu.');
      }
    }
  };

  const handleSetDefault = async (id) => {
    try {
      await setDefaultBrand(id);
      loadBrands();
    } catch (err) {
      alert(err.message || 'Lỗi khi đặt mặc định.');
    }
  };

  const handleToggleStatus = async (id, currentStatus) => {
    try {
      await toggleBrandStatus(id, !currentStatus);
      loadBrands();
    } catch (err) {
      alert('Lỗi khi cập nhật trạng thái.');
    }
  };

  return (
    <div className="post-list-page">
      <div className="page-header">
        <h2>Quản lý thương hiệu</h2>
        <Link to="/brands/new" className="btn-primary">Thêm thương hiệu</Link>
      </div>

      <div className="filters">
        <input 
          type="text" 
          placeholder="Tìm kiếm thương hiệu..." 
          value={search} 
          onChange={(e) => setSearch(e.target.value)} 
          className="search-input"
        />
        <select value={isActive} onChange={(e) => setIsActive(e.target.value)}>
          <option value="all">Tất cả trạng thái</option>
          <option value="true">Đang hoạt động</option>
          <option value="false">Tạm dừng</option>
        </select>
      </div>

      <div className="post-grid">
        {loading ? (
          <div className="loading">Đang tải danh sách thương hiệu...</div>
        ) : brands.length === 0 ? (
          <div className="empty-state">Chưa có thương hiệu nào.</div>
        ) : (
          brands.map(brand => (
            <div key={brand.id} className="post-card">
              <h4>{brand.name} {brand.is_default && <span className="badge-ready" style={{fontSize: 10, marginLeft: 5}}>Mặc định</span>}</h4>
              <p className="excerpt">
                <strong>Ngành hàng:</strong> {brand.industry || 'Chưa cập nhật'}<br/>
                <strong>Giọng văn:</strong> {brand.tone || 'Mặc định'}<br/>
                <strong>Template:</strong> {brand.templates_count || 0} mẫu
              </p>
              <div className="post-meta">
                <span className={`status ${brand.is_active ? 'badge-ready' : 'badge-draft'}`}>
                  {brand.is_active ? 'Đang hoạt động' : 'Tạm dừng'}
                </span>
              </div>
              <div className="post-actions" style={{ flexWrap: 'wrap' }}>
                <Link to={`/brands/${brand.id}/edit`} className="btn-secondary">Sửa</Link>
                <Link to={`/brands/${brand.id}/templates`} className="btn-secondary">Templates</Link>
                <button onClick={() => handleToggleStatus(brand.id, brand.is_active)} className="btn-secondary">
                  {brand.is_active ? 'Tắt' : 'Bật'}
                </button>
                {!brand.is_default && brand.is_active && (
                  <button onClick={() => handleSetDefault(brand.id)} className="btn-secondary">Mặc định</button>
                )}
                <button onClick={() => handleDelete(brand.id, brand.name)} className="btn-danger">Xóa</button>
              </div>
            </div>
          ))
        )}
      </div>

      {meta && meta.last_page > 1 && (
        <div className="pagination">
          <button disabled={page === 1} onClick={() => setPage(p => p - 1)} className="btn-secondary">Trang trước</button>
          <span>Trang {page} / {meta.last_page}</span>
          <button disabled={page === meta.last_page} onClick={() => setPage(p => p + 1)} className="btn-secondary">Trang sau</button>
        </div>
      )}
    </div>
  );
};

export default BrandList;
