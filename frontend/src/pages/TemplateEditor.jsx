import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { fetchTemplate, createTemplate, updateTemplate } from '../api/templates';

const TemplateEditor = () => {
  const { brandId, templateId } = useParams();
  const navigate = useNavigate();
  const isNew = !templateId;

  const [loading, setLoading] = useState(!isNew);
  const [saving, setSaving] = useState(false);
  const [errorMsg, setErrorMsg] = useState(null);
  
  const [template, setTemplate] = useState({
    name: '', description: '', objective: 'sales', opening_style: '', body_structure: '', 
    cta_instruction: '', hashtag_instruction: '', additional_instruction: '', example_content: '',
    is_default: false, is_active: true
  });

  useEffect(() => {
    if (!isNew) {
      loadTemplate();
    }
  }, [templateId, isNew]);

  const loadTemplate = async () => {
    try {
      const res = await fetchTemplate(brandId, templateId);
      const data = res.data;
      setTemplate({
        ...data,
        body_structure: Array.isArray(data.body_structure) ? data.body_structure.join('\n') : '',
      });
    } catch (err) {
      alert('Không thể tải mẫu nội dung.');
      navigate(`/brands/${brandId}/templates`);
    } finally {
      setLoading(false);
    }
  };

  const handleChange = (e) => {
    const { name, value, type, checked } = e.target;
    setTemplate(prev => ({ ...prev, [name]: type === 'checkbox' ? checked : value }));
  };

  const saveTemplate = async (e) => {
    e.preventDefault();
    setSaving(true);
    setErrorMsg(null);
    
    try {
      const payload = {
        ...template,
        body_structure: template.body_structure ? template.body_structure.split('\n').map(s=>s.trim()).filter(s=>s) : []
      };
      
      if (isNew) {
        await createTemplate(brandId, payload);
        alert('Tạo mẫu thành công!');
      } else {
        await updateTemplate(brandId, templateId, payload);
        alert('Cập nhật mẫu thành công!');
      }
      navigate(`/brands/${brandId}/templates`);
    } catch (err) {
      if (err.errors) {
        const msgs = Object.values(err.errors).flat().join(' ');
        setErrorMsg(msgs);
      } else {
        setErrorMsg(err.message || 'Lỗi lưu mẫu nội dung');
      }
    } finally {
      setSaving(false);
    }
  };

  if (loading) return <div className="loading">Đang tải...</div>;

  return (
    <div className="post-editor-page">
      <div className="editor-header">
        <div className="header-left">
          <button className="btn-secondary" onClick={() => navigate(`/brands/${brandId}/templates`)}>&larr; Quay lại</button>
          <h2>{isNew ? 'Thêm mẫu nội dung' : 'Chỉnh sửa mẫu'}</h2>
        </div>
      </div>
      
      {errorMsg && <div className="error-alert">{errorMsg}</div>}

      <form onSubmit={saveTemplate} className="editor-layout" style={{gridTemplateColumns: '1fr'}}>
        <div className="form-section">
          <h3>Thông tin chung</h3>
          <div className="editor-layout">
            <div className="form-group">
              <label>Tên mẫu *</label>
              <input type="text" name="name" value={template.name} onChange={handleChange} required />
            </div>
            <div className="form-group">
              <label>Mục tiêu bài viết *</label>
              <select name="objective" value={template.objective} onChange={handleChange} required>
                <option value="sales">Bán hàng</option>
                <option value="introduction">Giới thiệu sản phẩm</option>
                <option value="promotion">Chương trình ưu đãi</option>
                <option value="engagement">Tăng tương tác</option>
                <option value="education">Chia sẻ kiến thức</option>
                <option value="event">Quảng bá sự kiện</option>
              </select>
            </div>
            <div className="form-group" style={{gridColumn: '1 / -1'}}>
              <label>Mô tả (Dùng trong nội bộ)</label>
              <textarea name="description" value={template.description} onChange={handleChange} rows="2" />
            </div>
          </div>
          
          <h3 style={{marginTop: 30}}>Cấu trúc nội dung</h3>
          <div className="editor-layout">
            <div className="form-group">
              <label>Phong cách mở bài</label>
              <input type="text" name="opening_style" value={template.opening_style} onChange={handleChange} placeholder="Ví dụ: Đặt câu hỏi gây tò mò, Nêu ngay vấn đề..." />
            </div>
            <div className="form-group" style={{gridColumn: '1 / -1'}}>
              <label>Cấu trúc thân bài (Mỗi phần 1 dòng)</label>
              <textarea name="body_structure" value={template.body_structure} onChange={handleChange} rows="4" placeholder="Giới thiệu tính năng&#10;Lợi ích khách hàng nhận được" />
            </div>
            <div className="form-group">
              <label>Hướng dẫn Call To Action (CTA)</label>
              <input type="text" name="cta_instruction" value={template.cta_instruction} onChange={handleChange} placeholder="Ví dụ: Thúc giục mua ngay trong ngày..." />
            </div>
            <div className="form-group">
              <label>Hướng dẫn Hashtag</label>
              <input type="text" name="hashtag_instruction" value={template.hashtag_instruction} onChange={handleChange} placeholder="Ví dụ: Dùng hashtag ngắn gọn..." />
            </div>
            <div className="form-group" style={{gridColumn: '1 / -1'}}>
              <label>Chỉ dẫn bổ sung cho AI</label>
              <textarea name="additional_instruction" value={template.additional_instruction} onChange={handleChange} rows="3" placeholder="Ghi chú thêm bất kỳ gì cho AI..." />
            </div>
          </div>

          <h3 style={{marginTop: 30}}>Trạng thái</h3>
          <div className="editor-layout" style={{display: 'flex', gap: 20}}>
            <label style={{display: 'flex', alignItems: 'center', gap: 10, cursor: 'pointer'}}>
              <input type="checkbox" name="is_active" checked={template.is_active} onChange={handleChange} style={{width: 'auto'}} />
              Đang hoạt động
            </label>
            <label style={{display: 'flex', alignItems: 'center', gap: 10, cursor: 'pointer'}}>
              <input type="checkbox" name="is_default" checked={template.is_default} onChange={handleChange} style={{width: 'auto'}} />
              Mặc định cho mục tiêu này
            </label>
          </div>

          <div style={{marginTop: 40}}>
            <button type="submit" className="btn-primary" disabled={saving}>
              {saving ? 'Đang lưu...' : 'Lưu mẫu'}
            </button>
          </div>
        </div>
      </form>
    </div>
  );
};

export default TemplateEditor;
