import React, { useState, useEffect } from 'react';
import { fetchBrands } from '../api/brands';
import { fetchTemplates } from '../api/templates';

const ContentForm = ({ onSubmit, loading }) => {
  const [formData, setFormData] = useState({
    brand_id: '',
    content_template_id: '',
    topic: '',
    main_information: '',
    target_audience: '',
    objective: 'sales',
    tone: 'friendly',
    length: 'medium',
    required_keywords: '',
    excluded_content: '',
    number_of_versions: 3
  });

  const [brands, setBrands] = useState([]);
  const [templates, setTemplates] = useState([]);
  const [loadingBrands, setLoadingBrands] = useState(false);
  const [loadingTemplates, setLoadingTemplates] = useState(false);

  useEffect(() => {
    const loadBrands = async () => {
      setLoadingBrands(true);
      try {
        const res = await fetchBrands({ is_active: true, compact: true });
        setBrands(res.data);
        
        // Auto select default brand
        const defaultBrand = res.data.find(b => b.is_default);
        if (defaultBrand) {
          setFormData(prev => ({ ...prev, brand_id: defaultBrand.id.toString() }));
        }
      } catch (err) {
        console.error('Lỗi tải thương hiệu', err);
      } finally {
        setLoadingBrands(false);
      }
    };
    loadBrands();
  }, []);

  useEffect(() => {
    if (formData.brand_id) {
      const loadTemplates = async () => {
        setLoadingTemplates(true);
        try {
          const res = await fetchTemplates(formData.brand_id, { is_active: true });
          setTemplates(res.data);
          
          // Auto select default template for current objective
          const defaultTpl = res.data.find(t => t.is_default && t.objective === formData.objective);
          if (defaultTpl) {
            setFormData(prev => ({ ...prev, content_template_id: defaultTpl.id.toString() }));
          } else {
            setFormData(prev => ({ ...prev, content_template_id: '' }));
          }
        } catch (err) {
          console.error('Lỗi tải mẫu nội dung', err);
        } finally {
          setLoadingTemplates(false);
        }
      };
      loadTemplates();
    } else {
      setTemplates([]);
      setFormData(prev => ({ ...prev, content_template_id: '' }));
    }
  }, [formData.brand_id, formData.objective]);

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    
    const payload = {
      ...formData,
      brand_id: formData.brand_id ? parseInt(formData.brand_id) : null,
      content_template_id: formData.content_template_id ? parseInt(formData.content_template_id) : null,
      number_of_versions: parseInt(formData.number_of_versions, 10),
      required_keywords: formData.required_keywords ? formData.required_keywords.split(',').map(s => s.trim()).filter(s => s) : [],
      excluded_content: formData.excluded_content ? formData.excluded_content.split('\n').map(s => s.trim()).filter(s => s) : []
    };

    onSubmit(payload);
  };

  const isFormValid = formData.topic.trim().length > 0 && formData.main_information.trim().length > 0;

  return (
    <form className="content-form" onSubmit={handleSubmit}>
      <div className="form-row">
        <div className="form-group half">
          <label>Thương hiệu (Hồ sơ AI)</label>
          <select name="brand_id" value={formData.brand_id} onChange={handleChange} disabled={loading || loadingBrands}>
            <option value="">-- Không chọn thương hiệu --</option>
            {brands.map(b => (
              <option key={b.id} value={b.id}>{b.name}</option>
            ))}
          </select>
        </div>

        <div className="form-group half">
          <label>Mẫu nội dung (Dành cho thương hiệu này)</label>
          <select name="content_template_id" value={formData.content_template_id} onChange={handleChange} disabled={loading || loadingTemplates || !formData.brand_id}>
            <option value="">-- Không sử dụng mẫu --</option>
            {templates.filter(t => t.objective === formData.objective).map(t => (
              <option key={t.id} value={t.id}>{t.name}</option>
            ))}
          </select>
          {formData.brand_id && templates.filter(t => t.objective === formData.objective).length === 0 && (
            <small style={{color: 'gray'}}>Không có mẫu nào cho mục tiêu này.</small>
          )}
        </div>
      </div>

      <div className="form-group">
        <label>Tên sản phẩm hoặc chủ đề <span className="required">*</span></label>
        <input type="text" name="topic" value={formData.topic} onChange={handleChange} maxLength="150" required placeholder="Ví dụ: Mì Omachi sườn hầm ngũ quả" disabled={loading} />
        <small>{formData.topic.length}/150 ký tự</small>
      </div>

      <div className="form-group">
        <label>Thông tin chính <span className="required">*</span></label>
        <textarea name="main_information" value={formData.main_information} onChange={handleChange} maxLength="5000" rows="4" required placeholder="Đặc điểm, lợi ích, giá, chương trình ưu đãi..." disabled={loading}></textarea>
        <small>{formData.main_information.length}/5000 ký tự</small>
      </div>

      <div className="form-group">
        <label>Khách hàng mục tiêu</label>
        <input type="text" name="target_audience" value={formData.target_audience} onChange={handleChange} maxLength="1000" placeholder="Ví dụ: Sinh viên 18–24 tuổi. Sẽ tự động lấy từ thương hiệu nếu bỏ trống." disabled={loading} />
      </div>

      <div className="form-row">
        <div className="form-group half">
          <label>Mục tiêu bài viết</label>
          <select name="objective" value={formData.objective} onChange={handleChange} disabled={loading}>
            <option value="sales">Bán hàng</option>
            <option value="introduction">Giới thiệu sản phẩm</option>
            <option value="promotion">Chương trình ưu đãi</option>
            <option value="engagement">Tăng tương tác</option>
            <option value="education">Chia sẻ kiến thức</option>
            <option value="event">Quảng bá sự kiện</option>
          </select>
        </div>

        <div className="form-group half">
          <label>Giọng văn</label>
          <select name="tone" value={formData.tone} onChange={handleChange} disabled={loading}>
            <option value="professional">Chuyên nghiệp</option>
            <option value="friendly">Thân thiện</option>
            <option value="youthful">Trẻ trung</option>
            <option value="humorous">Hài hước</option>
            <option value="luxurious">Sang trọng</option>
            <option value="inspirational">Truyền cảm hứng</option>
          </select>
        </div>
      </div>

      <div className="form-row">
        <div className="form-group half">
          <label>Độ dài</label>
          <select name="length" value={formData.length} onChange={handleChange} disabled={loading}>
            <option value="short">Ngắn (80–120 từ)</option>
            <option value="medium">Trung bình (150–250 từ)</option>
            <option value="long">Dài (300–450 từ)</option>
          </select>
        </div>

        <div className="form-group half">
          <label>Số lượng phiên bản</label>
          <input type="number" name="number_of_versions" value={formData.number_of_versions} onChange={handleChange} min="1" max="5" disabled={loading} />
        </div>
      </div>

      <div className="form-group">
        <label>Từ khóa bắt buộc</label>
        <input type="text" name="required_keywords" value={formData.required_keywords} onChange={handleChange} placeholder="Phân tách bằng dấu phẩy (Ví dụ: Omachi, mì khoai tây)" disabled={loading} />
      </div>

      <div className="form-group">
        <label>Nội dung cần tránh</label>
        <textarea name="excluded_content" value={formData.excluded_content} onChange={handleChange} rows="3" placeholder="Mỗi yêu cầu nằm trên một dòng. Ví dụ: Không nhắc đến đối thủ" disabled={loading}></textarea>
      </div>

      <button type="submit" className="btn-primary" disabled={loading || !isFormValid}>
        {loading ? 'Đang tạo nội dung...' : 'Tạo nội dung'}
      </button>
    </form>
  );
};

export default ContentForm;
