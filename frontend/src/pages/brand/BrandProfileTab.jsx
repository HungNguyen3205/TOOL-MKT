import React, { useState } from 'react';
import { updateBrand, createBrand } from '../../api/brands';

const steps = [
  { id: 1, label: 'Thông tin cơ bản' },
  { id: 2, label: 'Sản phẩm & Định vị' },
  { id: 3, label: 'Khách hàng mục tiêu' },
  { id: 4, label: 'Giọng văn & Quy tắc' },
  { id: 5, label: 'CTA & Hashtag' },
];

const BrandProfileTab = ({ brand, setBrand, isNew, onSaved }) => {
  const [currentStep, setCurrentStep] = useState(1);
  const [saving, setSaving] = useState(false);
  const [errorMsg, setErrorMsg] = useState(null);

  const handleChange = (e) => {
    const { name, value, type, checked } = e.target;
    setBrand(prev => ({ ...prev, [name]: type === 'checkbox' ? checked : value }));
  };

  const handleArrayChange = (e, field) => {
    setBrand(prev => ({ ...prev, [field]: e.target.value }));
  };

  const saveBrand = async (e) => {
    if (e) e.preventDefault();
    setSaving(true);
    setErrorMsg(null);
    
    try {
      const payload = {
        ...brand,
        service_areas: typeof brand.service_areas === 'string' ? brand.service_areas.split(',').map(s=>s.trim()).filter(s=>s) : brand.service_areas,
        competitive_advantages: typeof brand.competitive_advantages === 'string' ? brand.competitive_advantages.split('\n').map(s=>s.trim()).filter(s=>s) : brand.competitive_advantages,
        customer_pain_points: typeof brand.customer_pain_points === 'string' ? brand.customer_pain_points.split('\n').map(s=>s.trim()).filter(s=>s) : brand.customer_pain_points,
        customer_desires: typeof brand.customer_desires === 'string' ? brand.customer_desires.split('\n').map(s=>s.trim()).filter(s=>s) : brand.customer_desires,
        customer_objections: typeof brand.customer_objections === 'string' ? brand.customer_objections.split('\n').map(s=>s.trim()).filter(s=>s) : brand.customer_objections,
        platform_rules: typeof brand.platform_rules === 'string' ? brand.platform_rules.split('\n').map(s=>s.trim()).filter(s=>s) : brand.platform_rules,
        default_hashtags: typeof brand.default_hashtags === 'string' ? brand.default_hashtags.split(',').map(s=>s.trim()).filter(s=>s) : brand.default_hashtags,
        required_keywords: typeof brand.required_keywords === 'string' ? brand.required_keywords.split(',').map(s=>s.trim()).filter(s=>s) : brand.required_keywords,
        prohibited_terms: typeof brand.prohibited_terms === 'string' ? brand.prohibited_terms.split(',').map(s=>s.trim()).filter(s=>s) : brand.prohibited_terms,
        writing_rules: typeof brand.writing_rules === 'string' ? brand.writing_rules.split('\n').map(s=>s.trim()).filter(s=>s) : brand.writing_rules
      };
      
      let res;
      if (isNew) {
        res = await createBrand(payload);
        alert('Tạo thương hiệu thành công!');
      } else {
        res = await updateBrand(brand.id, payload);
        alert('Cập nhật thương hiệu thành công!');
      }
      if (onSaved) onSaved(res.data);
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

  const nextStep = () => setCurrentStep(prev => Math.min(prev + 1, 5));
  const prevStep = () => setCurrentStep(prev => Math.max(prev - 1, 1));

  const renderStepNav = () => (
    <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '20px', borderBottom: '1px solid var(--border)', paddingBottom: '10px' }}>
      {steps.map(step => (
        <div key={step.id} 
             style={{ 
               cursor: 'pointer', 
               fontWeight: currentStep === step.id ? 'bold' : 'normal',
               color: currentStep === step.id ? 'var(--primary)' : 'var(--text-muted)'
             }}
             onClick={() => setCurrentStep(step.id)}>
          {step.id}. {step.label}
        </div>
      ))}
    </div>
  );

  const renderStep1 = () => (
    <div className="editor-layout">
      <div className="form-group">
        <label>Tên thương hiệu *</label>
        <input type="text" name="name" value={brand.name || ''} onChange={handleChange} required />
      </div>
      <div className="form-group">
        <label>Loại mô hình kinh doanh (Brand Type)</label>
        <input type="text" name="brand_type" value={brand.brand_type || ''} onChange={handleChange} placeholder="Ví dụ: Phòng Gym, Phần mềm quản lý, Spa..." />
        <small>Quan trọng: Khai báo đúng mô hình để AI viết đúng hướng.</small>
      </div>
      <div className="form-group">
        <label>Ngành nghề (Industry)</label>
        <input type="text" name="industry" value={brand.industry || ''} onChange={handleChange} placeholder="Ví dụ: Sức khỏe & Thể hình, Công nghệ..." />
      </div>
      <div className="form-group">
        <label>Khu vực phục vụ (Cách nhau dấu phẩy)</label>
        <input type="text" value={brand.service_areas || ''} onChange={e => handleArrayChange(e, 'service_areas')} placeholder="Hà Nội, TP.HCM, Toàn quốc..." />
      </div>
      <div className="form-group">
        <label>Website</label>
        <input type="url" name="website" value={brand.website || ''} onChange={handleChange} placeholder="https://..." />
      </div>
      <div className="form-group">
        <label>Hotline</label>
        <input type="text" name="hotline" value={brand.hotline || ''} onChange={handleChange} />
      </div>
      <div className="form-group">
        <label>Email</label>
        <input type="email" name="email" value={brand.email || ''} onChange={handleChange} />
      </div>
      <div className="form-group">
        <label>Địa chỉ</label>
        <input type="text" name="address" value={brand.address || ''} onChange={handleChange} />
      </div>
      <div className="form-group" style={{gridColumn: '1 / -1'}}>
        <label style={{display: 'flex', alignItems: 'center', gap: 10, cursor: 'pointer'}}>
          <input type="checkbox" name="is_active" checked={brand.is_active} onChange={handleChange} style={{width: 'auto'}} />
          Đang hoạt động
        </label>
      </div>
    </div>
  );

  const renderStep2 = () => (
    <div className="editor-layout">
      <div className="form-group" style={{gridColumn: '1 / -1'}}>
        <label>Mô tả thương hiệu</label>
        <textarea name="description" value={brand.description || ''} onChange={handleChange} rows="3" />
      </div>
      <div className="form-group" style={{gridColumn: '1 / -1'}}>
        <label>Sản phẩm / Dịch vụ cốt lõi</label>
        <textarea name="products_services" value={brand.products_services || ''} onChange={handleChange} rows="3" />
      </div>
      <div className="form-group" style={{gridColumn: '1 / -1'}}>
        <label>Định vị thương hiệu (Positioning)</label>
        <textarea name="positioning" value={brand.positioning || ''} onChange={handleChange} rows="2" placeholder="Ví dụ: Phòng tập cao cấp dành cho dân văn phòng..." />
      </div>
      <div className="form-group" style={{gridColumn: '1 / -1'}}>
        <label>Điểm bán hàng độc nhất (Unique Value Proposition)</label>
        <textarea name="unique_value_proposition" value={brand.unique_value_proposition || ''} onChange={handleChange} rows="2" />
      </div>
      <div className="form-group" style={{gridColumn: '1 / -1'}}>
        <label>Lợi thế cạnh tranh (Mỗi dòng 1 ý)</label>
        <textarea value={brand.competitive_advantages || ''} onChange={e => handleArrayChange(e, 'competitive_advantages')} rows="3" />
      </div>
      <div className="form-group">
        <label>Slogan</label>
        <input type="text" name="slogan" value={brand.slogan || ''} onChange={handleChange} />
      </div>
      <div className="form-group" style={{gridColumn: '1 / -1'}}>
        <label>Câu chuyện thương hiệu (Brand Story)</label>
        <textarea name="brand_story" value={brand.brand_story || ''} onChange={handleChange} rows="3" />
      </div>
    </div>
  );

  const renderStep3 = () => (
    <div className="editor-layout">
      <div className="form-group" style={{gridColumn: '1 / -1'}}>
        <label>Khách hàng mục tiêu</label>
        <textarea name="target_audience" value={brand.target_audience || ''} onChange={handleChange} rows="3" placeholder="Giới trẻ 18-25 tuổi, dân văn phòng..." />
      </div>
      <div className="form-group" style={{gridColumn: '1 / -1'}}>
        <label>Nỗi đau của khách hàng (Mỗi dòng 1 ý)</label>
        <textarea value={brand.customer_pain_points || ''} onChange={e => handleArrayChange(e, 'customer_pain_points')} rows="3" />
      </div>
      <div className="form-group" style={{gridColumn: '1 / -1'}}>
        <label>Mong muốn của khách hàng (Mỗi dòng 1 ý)</label>
        <textarea value={brand.customer_desires || ''} onChange={e => handleArrayChange(e, 'customer_desires')} rows="3" />
      </div>
      <div className="form-group" style={{gridColumn: '1 / -1'}}>
        <label>Trở ngại khi mua hàng (Mỗi dòng 1 ý)</label>
        <textarea value={brand.customer_objections || ''} onChange={e => handleArrayChange(e, 'customer_objections')} rows="3" />
      </div>
    </div>
  );

  const renderStep4 = () => (
    <div className="editor-layout">
      <div className="form-group">
        <label>Giọng văn chung (Tone)</label>
        <select name="tone" value={brand.tone || 'friendly'} onChange={handleChange}>
          <option value="friendly">Thân thiện</option>
          <option value="professional">Chuyên nghiệp</option>
          <option value="youthful">Trẻ trung</option>
          <option value="humorous">Hài hước</option>
          <option value="luxurious">Sang trọng</option>
          <option value="inspirational">Truyền cảm hứng</option>
        </select>
      </div>
      <div className="form-group">
        <label>Tính cách thương hiệu (Brand Personality)</label>
        <input type="text" name="brand_personality" value={brand.brand_personality || ''} onChange={handleChange} placeholder="Hiện đại, năng động..." />
      </div>
      <div className="form-group">
        <label>Đại từ xưng hô (Preferred Addressing)</label>
        <input type="text" name="preferred_addressing" value={brand.preferred_addressing || ''} onChange={handleChange} placeholder="Gọi 'bạn', xưng 'chúng tôi'..." />
      </div>
      <div className="form-group">
        <label>Giới hạn Emoji</label>
        <input type="number" name="emoji_limit" value={brand.emoji_limit || ''} onChange={handleChange} placeholder="Ví dụ: 3" />
      </div>
      <div className="form-group" style={{gridColumn: '1 / -1'}}>
        <label>Quy tắc viết bài (Mỗi quy tắc 1 dòng)</label>
        <textarea value={brand.writing_rules || ''} onChange={e => handleArrayChange(e, 'writing_rules')} rows="3" placeholder="Không viết hoa toàn bộ tiêu đề..." />
      </div>
      <div className="form-group" style={{gridColumn: '1 / -1'}}>
        <label>Từ khóa bắt buộc phải có (Cách nhau dấu phẩy)</label>
        <textarea value={brand.required_keywords || ''} onChange={e => handleArrayChange(e, 'required_keywords')} rows="2" />
      </div>
      <div className="form-group" style={{gridColumn: '1 / -1'}}>
        <label>Từ/Nội dung CẤM tuyệt đối (Cách nhau dấu phẩy)</label>
        <textarea value={brand.prohibited_terms || ''} onChange={e => handleArrayChange(e, 'prohibited_terms')} rows="2" placeholder="Cam kết 100%, Trị bách bệnh..." />
      </div>
    </div>
  );

  const renderStep5 = () => (
    <div className="editor-layout">
      <div className="form-group" style={{gridColumn: '1 / -1'}}>
        <label>Call to Action (CTA) mặc định</label>
        <textarea name="default_cta" value={brand.default_cta || ''} onChange={handleChange} rows="2" placeholder="Liên hệ ngay hotline để nhận ưu đãi!" />
      </div>
      <div className="form-group" style={{gridColumn: '1 / -1'}}>
        <label>Hashtag mặc định (Cách nhau dấu phẩy)</label>
        <textarea value={brand.default_hashtags || ''} onChange={e => handleArrayChange(e, 'default_hashtags')} rows="2" placeholder="#Yoga, #Pilates" />
      </div>
      <div className="form-group" style={{gridColumn: '1 / -1'}}>
        <label>Quy tắc nền tảng (Platform Rules) (Mỗi dòng 1 ý)</label>
        <textarea value={brand.platform_rules || ''} onChange={e => handleArrayChange(e, 'platform_rules')} rows="3" placeholder="Quy tắc riêng cho Facebook..." />
      </div>
    </div>
  );

  return (
    <div>
      {errorMsg && <div className="error-alert">{errorMsg}</div>}
      
      {renderStepNav()}

      <form onSubmit={saveBrand}>
        <div style={{minHeight: '400px'}}>
          {currentStep === 1 && renderStep1()}
          {currentStep === 2 && renderStep2()}
          {currentStep === 3 && renderStep3()}
          {currentStep === 4 && renderStep4()}
          {currentStep === 5 && renderStep5()}
        </div>

        <div style={{ marginTop: 40, display: 'flex', justifyContent: 'space-between' }}>
          <button type="button" className="btn-secondary" onClick={prevStep} disabled={currentStep === 1}>
            &larr; Quay lại
          </button>
          
          <div>
            <button type="button" className="btn-secondary" onClick={saveBrand} disabled={saving} style={{marginRight: '10px'}}>
              {saving ? 'Đang lưu...' : 'Lưu'}
            </button>
            <button type="button" className="btn-primary" onClick={currentStep === 5 ? saveBrand : nextStep}>
              {currentStep === 5 ? (saving ? 'Đang hoàn tất...' : 'Hoàn tất') : 'Tiếp theo \u2192'}
            </button>
          </div>
        </div>
      </form>
    </div>
  );
};

export default BrandProfileTab;
