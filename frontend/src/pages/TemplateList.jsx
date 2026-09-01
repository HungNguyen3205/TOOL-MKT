import React, { useEffect, useState, useCallback } from 'react';
import { fetchTemplates, deleteTemplate, setDefaultTemplate, toggleTemplateStatus } from '../api/templates';
import { fetchBrand } from '../api/brands';
import { Link, useParams, useNavigate } from 'react-router-dom';

const TemplateList = () => {
  const { brandId } = useParams();
  const [templates, setTemplates] = useState([]);
  const [brand, setBrand] = useState(null);
  const [loading, setLoading] = useState(true);
  const navigate = useNavigate();

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const [brandRes, templatesRes] = await Promise.all([
        fetchBrand(brandId),
        fetchTemplates(brandId)
      ]);
      setBrand(brandRes.data);
      setTemplates(templatesRes.data);
    } catch (err) {
      alert('Lỗi tải dữ liệu mẫu nội dung.');
      navigate('/brands');
    } finally {
      setLoading(false);
    }
  }, [brandId, navigate]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleDelete = async (id, name) => {
    if (window.confirm(`Xóa mẫu "${name}"?`)) {
      try {
        await deleteTemplate(brandId, id);
        loadData();
      } catch (err) {
        alert('Lỗi xóa mẫu.');
      }
    }
  };

  const handleSetDefault = async (id) => {
    try {
      await setDefaultTemplate(brandId, id);
      loadData();
    } catch (err) {
      alert(err.message || 'Lỗi đặt mặc định.');
    }
  };

  const handleToggleStatus = async (id, current) => {
    try {
      await toggleTemplateStatus(brandId, id, !current);
      loadData();
    } catch (err) {
      alert('Lỗi cập nhật trạng thái.');
    }
  };

  const objectiveMap = {
    sales: 'Bán hàng',
    introduction: 'Giới thiệu sản phẩm',
    promotion: 'Chương trình ưu đãi',
    engagement: 'Tăng tương tác',
    education: 'Chia sẻ kiến thức',
    event: 'Quảng bá sự kiện'
  };

  if (loading) return <div className="loading">Đang tải...</div>;

  return (
    <div className="post-list-page">
      <div className="page-header">
        <div>
          <Link to="/brands" className="btn-secondary" style={{marginRight: 10}}>&larr;</Link>
          <h2 style={{display: 'inline-block', margin: 0}}>Mẫu nội dung: {brand?.name}</h2>
        </div>
        <Link to={`/brands/${brandId}/templates/new`} className="btn-primary">Thêm mẫu mới</Link>
      </div>

      <div className="post-grid">
        {templates.length === 0 ? (
          <div className="empty-state">Chưa có mẫu nào.</div>
        ) : (
          templates.map(tpl => (
            <div key={tpl.id} className="post-card">
              <h4>{tpl.name} {tpl.is_default && <span className="badge-ready" style={{fontSize: 10, marginLeft: 5}}>Mặc định</span>}</h4>
              <p className="excerpt">
                <strong>Mục tiêu:</strong> {objectiveMap[tpl.objective] || tpl.objective}<br/>
                <strong>Mô tả:</strong> {tpl.description || 'Không có'}
              </p>
              <div className="post-meta">
                <span className={`status ${tpl.is_active ? 'badge-ready' : 'badge-draft'}`}>
                  {tpl.is_active ? 'Đang hoạt động' : 'Tạm dừng'}
                </span>
              </div>
              <div className="post-actions" style={{ flexWrap: 'wrap' }}>
                <Link to={`/brands/${brandId}/templates/${tpl.id}/edit`} className="btn-secondary">Sửa</Link>
                <button onClick={() => handleToggleStatus(tpl.id, tpl.is_active)} className="btn-secondary">
                  {tpl.is_active ? 'Tắt' : 'Bật'}
                </button>
                {!tpl.is_default && tpl.is_active && (
                  <button onClick={() => handleSetDefault(tpl.id)} className="btn-secondary">Mặc định</button>
                )}
                <button onClick={() => handleDelete(tpl.id, tpl.name)} className="btn-danger">Xóa</button>
              </div>
            </div>
          ))
        )}
      </div>
    </div>
  );
};

export default TemplateList;
