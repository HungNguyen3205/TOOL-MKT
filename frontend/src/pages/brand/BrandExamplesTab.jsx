import React, { useState, useEffect } from 'react';
import { fetchBrandExamples, createBrandExample, updateBrandExample, deleteBrandExample } from '../../api/brands';

const BrandExamplesTab = ({ brandId }) => {
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [formData, setFormData] = useState({ id: null, title: '', example_type: 'good', content: '', explanation: '', objective: '', is_active: true });

  useEffect(() => {
    loadItems();
  }, [brandId]);

  const loadItems = async () => {
    setLoading(true);
    try {
      const res = await fetchBrandExamples(brandId);
      setItems(res.data);
    } catch (err) {
      alert('Không tải được bài mẫu.');
    } finally {
      setLoading(false);
    }
  };

  const handleEdit = (item) => {
    setFormData(item);
    setShowForm(true);
  };

  const handleDelete = async (id) => {
    if (!window.confirm('Xóa bài mẫu này?')) return;
    try {
      await deleteBrandExample(brandId, id);
      loadItems();
    } catch (err) {
      alert('Lỗi xóa.');
    }
  };

  const saveItem = async (e) => {
    e.preventDefault();
    try {
      if (formData.id) {
        await updateBrandExample(brandId, formData.id, formData);
      } else {
        await createBrandExample(brandId, formData);
      }
      setShowForm(false);
      loadItems();
    } catch (err) {
      alert('Lỗi lưu bài mẫu.');
    }
  };

  if (loading) return <div>Đang tải...</div>;

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 20 }}>
        <h3>Bài viết mẫu</h3>
        {!showForm && <button className="btn-primary" onClick={() => { setFormData({ id: null, title: '', example_type: 'good', content: '', explanation: '', objective: '', is_active: true }); setShowForm(true); }}>+ Thêm bài mẫu</button>}
      </div>

      {showForm && (
        <form onSubmit={saveItem} style={{ backgroundColor: '#f9f9f9', padding: 20, borderRadius: 8, marginBottom: 20 }}>
          <h4>{formData.id ? 'Sửa bài mẫu' : 'Thêm mới'}</h4>
          <div className="editor-layout">
            <div className="form-group">
              <label>Tiêu đề</label>
              <input type="text" value={formData.title} onChange={e => setFormData({...formData, title: e.target.value})} required />
            </div>
            <div className="form-group">
              <label>Loại mẫu (Nên hay Không nên)</label>
              <select value={formData.example_type} onChange={e => setFormData({...formData, example_type: e.target.value})}>
                <option value="good">Nên dùng (Mẫu tốt)</option>
                <option value="bad">Không nên dùng (Mẫu xấu)</option>
              </select>
            </div>
            <div className="form-group" style={{gridColumn: '1 / -1'}}>
              <label>Nội dung bài viết</label>
              <textarea value={formData.content} onChange={e => setFormData({...formData, content: e.target.value})} rows="6" required />
            </div>
            <div className="form-group" style={{gridColumn: '1 / -1'}}>
              <label>Giải thích lý do ({formData.example_type === 'good' ? 'Tại sao mẫu này tốt?' : 'Tại sao không nên viết thế này?'})</label>
              <textarea value={formData.explanation} onChange={e => setFormData({...formData, explanation: e.target.value})} rows="2" />
            </div>
          </div>
          <div style={{ marginTop: 20, display: 'flex', gap: 10 }}>
            <button type="submit" className="btn-primary">Lưu lại</button>
            <button type="button" className="btn-secondary" onClick={() => setShowForm(false)}>Hủy</button>
          </div>
        </form>
      )}

      <div style={{ display: 'flex', flexDirection: 'column', gap: 15 }}>
        {items.length === 0 ? <p>Chưa có bài mẫu nào.</p> : items.map(item => (
          <div key={item.id} style={{ border: item.example_type === 'good' ? '1px solid #4CAF50' : '1px solid #F44336', padding: 15, borderRadius: 8 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <h4 style={{ margin: '0 0 10px 0', color: item.example_type === 'good' ? '#2e7d32' : '#c62828' }}>
                {item.example_type === 'good' ? '✅' : '❌'} {item.title}
              </h4>
              <div>
                <button onClick={() => handleEdit(item)} style={{ background: 'none', border: 'none', color: '#0066cc', cursor: 'pointer', marginRight: 10 }}>Sửa</button>
                <button onClick={() => handleDelete(item.id)} style={{ background: 'none', border: 'none', color: '#cc0000', cursor: 'pointer' }}>Xóa</button>
              </div>
            </div>
            <pre style={{ margin: '10px 0', padding: 10, background: '#f5f5f5', whiteSpace: 'pre-wrap', fontFamily: 'inherit', fontSize: '14px' }}>
              {item.content}
            </pre>
            {item.explanation && (
              <p style={{ margin: 0, color: '#666', fontStyle: 'italic' }}>* Giải thích: {item.explanation}</p>
            )}
          </div>
        ))}
      </div>
    </div>
  );
};

export default BrandExamplesTab;
