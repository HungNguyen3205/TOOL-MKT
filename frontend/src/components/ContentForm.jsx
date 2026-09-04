import React, { useState, useEffect, useRef } from 'react';
import { fetchBrands, fetchBrand } from '../api/brands';
import { fetchTemplates, resolveTemplate } from '../api/templates';

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
    cta_instruction: '',
    hashtag_instruction: '',
    number_of_versions: 3,
    use_contact_info: false
  });

  const [brands, setBrands] = useState([]);
  const [templates, setTemplates] = useState([]);
  const [loadingBrands, setLoadingBrands] = useState(false);
  const [loadingTemplates, setLoadingTemplates] = useState(false);
  
  const [isAdvancedOpen, setIsAdvancedOpen] = useState(false);
  const isAdvancedEditedByUser = useRef(false);
  
  const [currentBrandData, setCurrentBrandData] = useState(null);
  const [brandSuccessMsg, setBrandSuccessMsg] = useState('');
  
  const [confirmModal, setConfirmModal] = useState({ isOpen: false, pendingBrandId: null });

  useEffect(() => {
    const loadBrands = async () => {
      setLoadingBrands(true);
      try {
        const res = await fetchBrands({ is_active: true, compact: true });
        setBrands(res.data);
        
        const defaultBrand = res.data.find(b => b.is_default);
        if (defaultBrand) {
          processBrandChange(defaultBrand.id.toString(), true);
        }
      } catch (err) {
        console.error('Lỗi tải thương hiệu', err);
      } finally {
        setLoadingBrands(false);
      }
    };
    loadBrands();
  }, []);

  const handleBrandChangeInit = (brandIdStr) => {
    if (isAdvancedEditedByUser.current) {
      setConfirmModal({ isOpen: true, pendingBrandId: brandIdStr });
    } else {
      processBrandChange(brandIdStr, true);
    }
  };

  const processBrandChange = async (brandIdStr, shouldOverwrite) => {
    setConfirmModal({ isOpen: false, pendingBrandId: null });
    
    setFormData(prev => ({ ...prev, brand_id: brandIdStr, content_template_id: '' }));
    
    if (!brandIdStr) {
      setTemplates([]);
      setCurrentBrandData(null);
      return;
    }

    try {
      const brandRes = await fetchBrand(brandIdStr);
      const brandFull = brandRes.data;
      setCurrentBrandData(brandFull);
      
      setBrandSuccessMsg(`Đã áp dụng hồ sơ: ${brandFull.name}`);
      setTimeout(() => setBrandSuccessMsg(''), 4000);

      setLoadingTemplates(true);
      const templatesRes = await fetchTemplates(brandIdStr, { is_active: true });
      setTemplates(templatesRes.data);
      setLoadingTemplates(false);

      // Auto resolve template
      const bestTplRes = await resolveTemplate(brandIdStr, { objective: formData.objective, content_type: 'post' });
      const bestTpl = bestTplRes.data;

      if (shouldOverwrite) {
        applyAutoFill(brandFull, bestTpl);
      } else {
        if (bestTpl) {
           setFormData(prev => ({ ...prev, content_template_id: bestTpl.id.toString() }));
        }
      }

    } catch (err) {
      console.error('Lỗi khi tải thông tin thương hiệu', err);
      setLoadingTemplates(false);
    }
  };

  const handleTemplateChange = (templateIdStr) => {
    if (isAdvancedEditedByUser.current) {
      if (!window.confirm('Bạn có muốn áp dụng dữ liệu của mẫu nội dung này và ghi đè các chỉnh sửa của bạn không?')) {
        setFormData(prev => ({ ...prev, content_template_id: templateIdStr }));
        return;
      }
    }

    setFormData(prev => ({ ...prev, content_template_id: templateIdStr }));
    
    if (currentBrandData) {
      const selectedTpl = templates.find(t => t.id.toString() === templateIdStr) || null;
      applyAutoFill(currentBrandData, selectedTpl);
    }
  };

  const handleObjectiveChange = async (e) => {
    const newObjective = e.target.value;
    setFormData(prev => ({ ...prev, objective: newObjective }));
    
    if (currentBrandData && formData.brand_id) {
        try {
            const bestTplRes = await resolveTemplate(formData.brand_id, { objective: newObjective, content_type: 'post' });
            const bestTpl = bestTplRes.data;

            if (bestTpl && !isAdvancedEditedByUser.current) {
                setFormData(prev => ({ ...prev, content_template_id: bestTpl.id.toString() }));
                applyAutoFill(currentBrandData, bestTpl);
            } else if (bestTpl) {
                setFormData(prev => ({ ...prev, content_template_id: bestTpl.id.toString() }));
            }
        } catch (error) {
            console.error('Lỗi auto resolve template', error);
        }
    }
  };

  const applyAutoFill = (brand, template) => {
    const newAudience = brand?.target_audience || '';
    const newTone = brand?.tone || 'friendly';
    
    const newCta = template?.cta_instruction || brand?.default_cta || '';
    const newHashtags = template?.hashtag_instruction || (brand?.default_hashtags ? brand.default_hashtags.join(' ') : '');
    
    const newKeywords = brand?.required_keywords ? brand.required_keywords.join(', ') : '';
    const newExcluded = brand?.prohibited_terms ? brand.prohibited_terms.join('\n') : '';

    setFormData(prev => ({
      ...prev,
      target_audience: newAudience,
      tone: newTone,
      cta_instruction: newCta,
      hashtag_instruction: newHashtags,
      required_keywords: newKeywords,
      excluded_content: newExcluded,
      use_contact_info: true // Automatically check if a brand is loaded
    }));

    if (template) {
       setFormData(prev => ({ ...prev, content_template_id: template.id.toString() }));
    }

    isAdvancedEditedByUser.current = false;
  };

  const handleChange = (e) => {
    const { name, value, type, checked } = e.target;
    setFormData(prev => ({ ...prev, [name]: type === 'checkbox' ? checked : value }));
  };

  const handleAdvancedChange = (e) => {
    isAdvancedEditedByUser.current = true;
    handleChange(e);
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

  // Placeholder Logic
  const isDanava = currentBrandData?.name?.toLowerCase().includes('danava') || currentBrandData?.industry?.toLowerCase().includes('phần mềm');
  const isGym = currentBrandData?.brand_type?.toLowerCase().match(/gym|yoga|pilates/i) || currentBrandData?.name?.toLowerCase().match(/gym|yoga|pilates/i);

  let topicPlaceholder = 'Ví dụ: Khuyến mãi mùa lễ, Mở lớp học mới...';
  let infoPlaceholder = 'Nhập thông tin chi tiết về sản phẩm/dịch vụ bạn muốn đưa vào bài viết...';

  if (isDanava) {
    topicPlaceholder = 'Ví dụ: Tính năng quản lý học viên mới, Cập nhật ứng dụng...';
    infoPlaceholder = 'Nhập tính năng phần mềm, giải pháp vận hành, cách check-in...';
  } else if (isGym) {
    topicPlaceholder = 'Ví dụ: Ưu đãi tập Gym tháng 9, Lớp học Pilates cơ bản...';
    infoPlaceholder = 'Nhập giá, thời gian áp dụng, quyền lợi, địa điểm tập...';
  }

  return (
    <>
      {confirmModal.isOpen && (
        <div style={{ position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, backgroundColor: 'rgba(0,0,0,0.7)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1000, padding: '20px' }}>
          <div style={{ backgroundColor: '#2d2d2d', padding: '20px', borderRadius: '8px', maxWidth: '400px', width: '100%', color: '#fff' }}>
            <h4 style={{ marginTop: 0, marginBottom: '15px' }}>Xác nhận chuyển thương hiệu</h4>
            <p style={{ marginBottom: '20px' }}>Bạn đã chỉnh sửa một số thông tin. Bạn có muốn ghi đè bằng dữ liệu của thương hiệu mới không?</p>
            <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
              <button className="btn-primary" onClick={() => processBrandChange(confirmModal.pendingBrandId, true)}>Ghi đè bằng dữ liệu mới</button>
              <button className="btn-secondary" onClick={() => processBrandChange(confirmModal.pendingBrandId, false)}>Giữ nội dung tôi đã nhập</button>
              <button className="btn-secondary" style={{ backgroundColor: '#555', color: 'white', borderColor: '#555' }} onClick={() => setConfirmModal({ isOpen: false, pendingBrandId: null })}>Hủy</button>
            </div>
          </div>
        </div>
      )}

      <form className="content-form" onSubmit={handleSubmit}>
        <div className="form-group">
          <label>Thương hiệu</label>
          <select name="brand_id" value={formData.brand_id} onChange={(e) => handleBrandChangeInit(e.target.value)} disabled={loading || loadingBrands}>
            <option value="">-- Chọn thương hiệu --</option>
            {brands.map(b => (
              <option key={b.id} value={b.id}>{b.name}</option>
            ))}
          </select>
          {brandSuccessMsg && <div style={{ color: '#4caf50', fontSize: '0.85rem', marginTop: '5px' }}>✓ {brandSuccessMsg}</div>}
        </div>

        <div className="form-group">
          <label>Chủ đề bài viết <span className="required">*</span></label>
          <input type="text" name="topic" value={formData.topic} onChange={handleChange} maxLength="150" required placeholder={topicPlaceholder} disabled={loading} />
        </div>

        <div className="form-group">
          <label>Thông tin chương trình <span className="required">*</span></label>
          <textarea name="main_information" value={formData.main_information} onChange={handleChange} maxLength="5000" rows="3" required placeholder={infoPlaceholder} disabled={loading}></textarea>
        </div>

        <div className="form-row">
          <div className="form-group half">
            <label>Mục tiêu bài viết</label>
            <select name="objective" value={formData.objective} onChange={handleObjectiveChange} disabled={loading}>
              <option value="sales">Bán hàng / Chốt sale</option>
              <option value="introduction">Giới thiệu sản phẩm/dịch vụ</option>
              <option value="promotion">Chương trình ưu đãi</option>
              <option value="engagement">Tăng tương tác</option>
              <option value="education">Chia sẻ kiến thức</option>
              <option value="event">Quảng bá sự kiện</option>
            </select>
          </div>

          <div className="form-group half">
            <label>Độ dài</label>
            <select name="length" value={formData.length} onChange={handleChange} disabled={loading}>
              <option value="short">Ngắn (80–120 từ)</option>
              <option value="medium">Trung bình (150–250 từ)</option>
              <option value="long">Dài (300–450 từ)</option>
            </select>
          </div>
        </div>

        <div className="advanced-options-container" style={{ marginTop: '10px', border: '1px solid #ddd', borderRadius: '8px', overflow: 'hidden' }}>
          <button type="button" onClick={() => setIsAdvancedOpen(!isAdvancedOpen)} style={{ width: '100%', padding: '12px 15px', backgroundColor: '#f5f5f5', border: 'none', textAlign: 'left', fontWeight: 'bold', cursor: 'pointer', display: 'flex', justifyContent: 'space-between' }}>
            <span>Tùy chỉnh nâng cao</span>
            <span>{isAdvancedOpen ? '▲' : '▼'}</span>
          </button>
          
          {isAdvancedOpen && (
            <div style={{ padding: '15px', backgroundColor: '#fafafa' }}>
              
              <div className="form-row">
                <div className="form-group half">
                  <label>Mẫu nội dung (Template)</label>
                  <select name="content_template_id" value={formData.content_template_id} onChange={(e) => handleTemplateChange(e.target.value)} disabled={loading || loadingTemplates || !formData.brand_id}>
                    <option value="">-- Không sử dụng mẫu --</option>
                    {templates.filter(t => t.objective === formData.objective).map(t => (
                      <option key={t.id} value={t.id}>{t.name}</option>
                    ))}
                  </select>
                </div>
                <div className="form-group half">
                  <label>Số lượng phiên bản</label>
                  <input type="number" name="number_of_versions" value={formData.number_of_versions} onChange={handleAdvancedChange} min="1" max="5" disabled={loading} />
                </div>
              </div>

              <div className="form-row">
                <div className="form-group half">
                  <label>Khách hàng mục tiêu</label>
                  <input type="text" name="target_audience" value={formData.target_audience} onChange={handleAdvancedChange} maxLength="1000" placeholder="Ví dụ: Sinh viên 18–24 tuổi..." disabled={loading} />
                </div>

                <div className="form-group half">
                  <label>Giọng văn</label>
                  <select name="tone" value={formData.tone} onChange={handleAdvancedChange} disabled={loading}>
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
                  <label>Hướng dẫn CTA</label>
                  <input type="text" name="cta_instruction" value={formData.cta_instruction} onChange={handleAdvancedChange} placeholder="Ví dụ: Kêu gọi click vào link..." disabled={loading} />
                </div>

                <div className="form-group half">
                  <label>Hashtag</label>
                  <input type="text" name="hashtag_instruction" value={formData.hashtag_instruction} onChange={handleAdvancedChange} placeholder="Ví dụ: #Yoga #Suckhoe" disabled={loading} />
                </div>
              </div>

              <div className="form-group">
                <label>Từ khóa bắt buộc</label>
                <input type="text" name="required_keywords" value={formData.required_keywords} onChange={handleAdvancedChange} placeholder="Phân tách bằng dấu phẩy" disabled={loading} />
              </div>

              <div className="form-group">
                <label>Nội dung cần tránh</label>
                <textarea name="excluded_content" value={formData.excluded_content} onChange={handleAdvancedChange} rows="2" placeholder="Mỗi yêu cầu nằm trên một dòng." disabled={loading}></textarea>
              </div>

              <div className="form-group" style={{marginTop: 10}}>
                <label style={{display: 'flex', alignItems: 'center', gap: 10, cursor: 'pointer', fontWeight: 'normal'}}>
                  <input type="checkbox" name="use_contact_info" checked={formData.use_contact_info} onChange={handleAdvancedChange} style={{width: 'auto'}} disabled={loading} />
                  Chèn thông tin liên hệ của thương hiệu vào bài viết
                </label>
              </div>
              
            </div>
          )}
        </div>

        <button type="submit" className="btn-primary" disabled={loading || !isFormValid} style={{ marginTop: '20px' }}>
          {loading ? 'Đang tạo nội dung...' : 'Tạo nội dung bằng AI'}
        </button>
      </form>
    </>
  );
};

export default ContentForm;
