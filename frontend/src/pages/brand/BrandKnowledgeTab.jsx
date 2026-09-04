import React, { useState, useEffect } from 'react';
import { fetchBrandKnowledge, createBrandKnowledge, updateBrandKnowledge, deleteBrandKnowledge } from '../../api/brands';

const BrandKnowledgeTab = ({ brandId }) => {
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [formData, setFormData] = useState({ id: null, title: '', category: 'product', content: '', source_url: '', priority: 0, is_active: true });

  useEffect(() => {
    loadItems();
  }, [brandId]);

  const loadItems = async () => {
    setLoading(true);
    try {
      const res = await fetchBrandKnowledge(brandId);
      setItems(res.data);
    } catch (err) {
      alert('Không tải được kiến thức.');
    } finally {
      setLoading(false);
    }
  };

  const handleEdit = (item) => {
    setFormData(item);
    setShowForm(true);
  };

  const handleDelete = async (id) => {
    if (!window.confirm('Xóa mục kiến thức này?')) return;
    try {
      await deleteBrandKnowledge(brandId, id);
      loadItems();
    } catch (err) {
      alert('Lỗi xóa.');
    }
  };

  const saveItem = async (e) => {
    e.preventDefault();
    try {
      if (formData.id) {
        await updateBrandKnowledge(brandId, formData.id, formData);
      } else {
        await createBrandKnowledge(brandId, formData);
      }
      setShowForm(false);
      loadItems();
    } catch (err) {
      alert('Lỗi lưu kiến thức.');
    }
  };

  if (loading) return <div>Đang tải...</div>;

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 20 }}>
        <h3>Kiến thức cốt lõi</h3>
        {!showForm && <button className="btn-primary" onClick={() => { setFormData({ id: null, title: '', category: 'product', content: '', source_url: '', priority: 0, is_active: true }); setShowForm(true); }}>+ Thêm kiến thức</button>}
      </div>

      {showForm && (
        <form onSubmit={saveItem} style={{ backgroundColor: '#f9f9f9', padding: 20, borderRadius: 8, marginBottom: 20 }}>
          <h4>{formData.id ? 'Sửa kiến thức' : 'Thêm mới'}</h4>
          <div className="editor-layout">
            <div className="form-group">
              <label>Tiêu đề</label>
              <input type="text" value={formData.title} onChange={e => setFormData({...formData, title: e.target.value})} required />
            </div>
            <div className="form-group">
              <label>Danh mục</label>
              <select value={formData.category} onChange={e => setFormData({...formData, category: e.target.value})}>
                <option value="product">Sản phẩm/Dịch vụ</option>
                <option value="promotion">Khuyến mãi/Chính sách</option>
                <option value="faq">Câu hỏi thường gặp</option>
                <option value="event">Sự kiện</option>
                <option value="other">Khác</option>
              </select>
            </div>
            <div className="form-group" style={{gridColumn: '1 / -1'}}>
              <label>Nội dung chi tiết</label>
              <textarea value={formData.content} onChange={e => setFormData({...formData, content: e.target.value})} rows="4" required />
            </div>
          </div>
          <div style={{ marginTop: 20, display: 'flex', gap: 10 }}>
            <button type="submit" className="btn-primary">Lưu lại</button>
            <button type="button" className="btn-secondary" onClick={() => setShowForm(false)}>Hủy</button>
          </div>
        </form>
      )}

      <div style={{ display: 'flex', flexDirection: 'column', gap: 15 }}>
        {items.length === 0 ? <p>Chưa có kiến thức nào.</p> : items.map(item => (
          <div key={item.id} style={{ border: '1px solid #eee', padding: 15, borderRadius: 8 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <h4 style={{ margin: '0 0 10px 0' }}>{item.title} <span style={{ fontSize: '12px', background: '#eee', padding: '2px 8px', borderRadius: 10 }}>{item.category}</span></h4>
              <div>
                <button onClick={() => handleEdit(item)} style={{ background: 'none', border: 'none', color: '#0066cc', cursor: 'pointer', marginRight: 10 }}>Sửa</button>
                <button onClick={() => handleDelete(item.id)} style={{ background: 'none', border: 'none', color: '#cc0000', cursor: 'pointer' }}>Xóa</button>
              </div>
            </div>
            <p style={{ margin: 0, color: '#444' }}>{item.content}</p>
          </div>
        ))}
      </div>
    </div>
  );
};

export default BrandKnowledgeTab;
