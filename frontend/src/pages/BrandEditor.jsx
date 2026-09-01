import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { fetchBrand, createBrand, updateBrand } from '../api/brands';

const BrandEditor = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  const isNew = !id;

  const [loading, setLoading] = useState(!isNew);
  const [saving, setSaving] = useState(false);
  const [errorMsg, setErrorMsg] = useState(null);
  
  const [brand, setBrand] = useState({
    name: '', slug: '', industry: '', description: '', products_services: '', target_audience: '', tone: 'friendly',
    slogan: '', default_cta: '', default_hashtags: '', required_keywords: '', prohibited_terms: '', writing_rules: '',
    is_default: false, is_active: true
  });

  useEffect(() => {
    if (!isNew) {
      loadBrand(id);
    }
  }, [id, isNew]);

  const loadBrand = async (brandId) => {
    try {
      const res = await fetchBrand(brandId);
      const data = res.data;
      setBrand({
        ...data,
        default_hashtags: Array.isArray(data.default_hashtags) ? data.default_hashtags.join(', ') : '',
        required_keywords: Array.isArray(data.required_keywords) ? data.required_keywords.join(', ') : '',
        prohibited_terms: Array.isArray(data.prohibited_terms) ? data.prohibited_terms.join(', ') : '',
        writing_rules: Array.isArray(data.writing_rules) ? data.writing_rules.join('\n') : '',
      });
    } catch (err) {
      alert('Không thể tải thương hiệu.');
      navigate('/brands');
    } finally {
      setLoading(false);
    }
  };

  const handleChange = (e) => {
    const { name, value, type, checked } = e.target;
    setBrand(prev => ({ ...prev, [name]: type === 'checkbox' ? checked : value }));
  };

  const saveBrand = async (e) => {
    e.preventDefault();
    setSaving(true);
    setErrorMsg(null);
    
    try {
      const payload = {
        ...brand,
        default_hashtags: brand.default_hashtags ? brand.default_hashtags.split(',').map(s=>s.trim()).filter(s=>s) : [],
        required_keywords: brand.required_keywords ? brand.required_keywords.split(',').map(s=>s.trim()).filter(s=>s) : [],
        prohibited_terms: brand.prohibited_terms ? brand.prohibited_terms.split(',').map(s=>s.trim()).filter(s=>s) : [],
        writing_rules: brand.writing_rules ? brand.writing_rules.split('\n').map(s=>s.trim()).filter(s=>s) : []
      };
      
      if (isNew) {
        await createBrand(payload);
        alert('Tạo thương hiệu thành công!');
      } else {
        await updateBrand(id, payload);
        alert('Cập nhật thương hiệu thành công!');
      }
      navigate('/brands');
    } catch (err) {
      if (err.errors) {
        const msgs = Object.values(err.errors).flat().join(' ');
        setErrorMsg(msgs);
      } else {
        setErrorMsg(err.message || 'Lỗi lưu thương hiệu');
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
          <button className="btn-secondary" onClick={() => navigate('/brands')}>&larr; Quay lại</button>
          <h2>{isNew ? 'Thêm thương hiệu' : 'Chỉnh sửa thương hiệu'}</h2>
        </div>
      </div>
      
      {errorMsg && <div className="error-alert">{errorMsg}</div>}

      <form onSubmit={saveBrand} className="editor-layout" style={{gridTemplateColumns: '1fr'}}>
        <div className="form-section">
          <h3>Thông tin chung</h3>
          <div className="editor-layout">
            <div className="form-group">
              <label>Tên thương hiệu *</label>
              <input type="text" name="name" value={brand.name} onChange={handleChange} required />
            </div>
            <div className="form-group">
              <label>Ngành hàng</label>
              <input type="text" name="industry" value={brand.industry} onChange={handleChange} placeholder="Ví dụ: Thực phẩm, Thời trang..." />
            </div>
            <div className="form-group" style={{gridColumn: '1 / -1'}}>
              <label>Mô tả thương hiệu</label>
              <textarea name="description" value={brand.description} onChange={handleChange} rows="3" />
            </div>
            <div className="form-group" style={{gridColumn: '1 / -1'}}>
              <label>Sản phẩm / Dịch vụ cốt lõi</label>
              <textarea name="products_services" value={brand.products_services} onChange={handleChange} rows="2" />
            </div>
          </div>
          
          <h3 style={{marginTop: 30}}>Khách hàng & Giọng văn</h3>
          <div className="editor-layout">
            <div className="form-group">
              <label>Khách hàng mục tiêu</label>
              <input type="text" name="target_audience" value={brand.target_audience} onChange={handleChange} placeholder="Giới trẻ 18-25 tuổi, thích ẩm thực..." />
            </div>
            <div className="form-group">
              <label>Giọng văn (Tone)</label>
              <select name="tone" value={brand.tone} onChange={handleChange}>
                <option value="friendly">Thân thiện</option>
                <option value="professional">Chuyên nghiệp</option>
                <option value="youthful">Trẻ trung</option>
                <option value="humorous">Hài hước</option>
                <option value="luxurious">Sang trọng</option>
                <option value="inspirational">Truyền cảm hứng</option>
              </select>
            </div>
            <div className="form-group">
              <label>Slogan</label>
              <input type="text" name="slogan" value={brand.slogan} onChange={handleChange} />
            </div>
            <div className="form-group">
              <label>Call to Action (CTA) mặc định</label>
              <input type="text" name="default_cta" value={brand.default_cta} onChange={handleChange} />
            </div>
          </div>

          <h3 style={{marginTop: 30}}>Quy tắc nội dung</h3>
          <div className="editor-layout">
            <div className="form-group">
              <label>Hashtag mặc định (Cách nhau bằng dấu phẩy)</label>
              <input type="text" name="default_hashtags" value={brand.default_hashtags} onChange={handleChange} placeholder="#Brand, #Tag2" />
            </div>
            <div className="form-group">
              <label>Từ khóa bắt buộc (Cách nhau bằng dấu phẩy)</label>
              <input type="text" name="required_keywords" value={brand.required_keywords} onChange={handleChange} />
            </div>
            <div className="form-group">
              <label>Từ/Nội dung CẤM (Cách nhau bằng dấu phẩy)</label>
              <input type="text" name="prohibited_terms" value={brand.prohibited_terms} onChange={handleChange} placeholder="Cam kết 100%, Trị bách bệnh..." />
            </div>
            <div className="form-group" style={{gridColumn: '1 / -1'}}>
              <label>Quy tắc viết bài (Mỗi quy tắc 1 dòng)</label>
              <textarea name="writing_rules" value={brand.writing_rules} onChange={handleChange} rows="4" placeholder="Không viết hoa toàn bộ tiêu đề&#10;Luôn gọi khách hàng là bạn" />
            </div>
          </div>

          <h3 style={{marginTop: 30}}>Trạng thái</h3>
          <div className="editor-layout" style={{display: 'flex', gap: 20}}>
            <label style={{display: 'flex', alignItems: 'center', gap: 10, cursor: 'pointer'}}>
              <input type="checkbox" name="is_active" checked={brand.is_active} onChange={handleChange} style={{width: 'auto'}} />
              Đang hoạt động
            </label>
            <label style={{display: 'flex', alignItems: 'center', gap: 10, cursor: 'pointer'}}>
              <input type="checkbox" name="is_default" checked={brand.is_default} onChange={handleChange} style={{width: 'auto'}} />
              Đặt làm thương hiệu mặc định
            </label>
          </div>

          <div style={{marginTop: 40}}>
            <button type="submit" className="btn-primary" disabled={saving}>
              {saving ? 'Đang lưu...' : 'Lưu thương hiệu'}
            </button>
          </div>
        </div>
      </form>
    </div>
  );
};

export default BrandEditor;
