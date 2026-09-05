import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { fetchBrand } from '../api/brands';
import BrandProfileTab from './brand/BrandProfileTab';
import BrandKnowledgeTab from './brand/BrandKnowledgeTab';
import BrandExamplesTab from './brand/BrandExamplesTab';
import BrandVersionsTab from './brand/BrandVersionsTab';

const BrandEditor = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  const isNew = !id;

  const [loading, setLoading] = useState(!isNew);
  const [activeTab, setActiveTab] = useState('profile');
  
  const [brand, setBrand] = useState({
    name: '', slug: '', industry: '', brand_type: '', description: '', products_services: '', target_audience: '', tone: 'friendly',
    slogan: '', default_cta: '', default_hashtags: '', required_keywords: '', prohibited_terms: '', writing_rules: '',
    service_areas: '', positioning: '', unique_value_proposition: '', brand_story: '', brand_personality: '',
    competitive_advantages: '', customer_pain_points: '', customer_desires: '', customer_objections: '',
    default_language: 'vi', emoji_limit: 3, preferred_addressing: '', platform_rules: '',
    website: '', hotline: '', email: '', address: '',
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
        service_areas: Array.isArray(data.service_areas) ? data.service_areas.join(', ') : '',
        competitive_advantages: Array.isArray(data.competitive_advantages) ? data.competitive_advantages.join('\n') : '',
        customer_pain_points: Array.isArray(data.customer_pain_points) ? data.customer_pain_points.join('\n') : '',
        customer_desires: Array.isArray(data.customer_desires) ? data.customer_desires.join('\n') : '',
        customer_objections: Array.isArray(data.customer_objections) ? data.customer_objections.join('\n') : '',
        platform_rules: Array.isArray(data.platform_rules) ? data.platform_rules.join('\n') : '',
      });
    } catch (err) {
      alert('Không thể tải thương hiệu.');
      navigate('/brands');
    } finally {
      setLoading(false);
    }
  };

  const tabs = [
    { id: 'profile', label: 'Hồ sơ thương hiệu' },
    { id: 'knowledge', label: 'Kiến thức cốt lõi', disabled: isNew },
    { id: 'examples', label: 'Bài viết mẫu', disabled: isNew },
    { id: 'versions', label: 'Lịch sử phiên bản', disabled: isNew },
  ];

  if (loading) return <div className="loading">Đang tải...</div>;

  return (
    <div className="post-editor-page">
      <div className="editor-header">
        <div className="header-left">
          <button className="btn-secondary" onClick={() => navigate('/brands')}>&larr; Quay lại</button>
          <h2>{isNew ? 'Thêm thương hiệu' : `Thương hiệu: ${brand.name}`}</h2>
        </div>
        {!isNew && brand.profile_completeness !== undefined && (
          <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
            <span style={{ fontWeight: 'bold' }}>Mức độ hoàn thiện:</span>
            <div style={{ width: 100, height: 10, background: '#eee', borderRadius: 5, overflow: 'hidden' }}>
              <div style={{ width: `${brand.profile_completeness}%`, height: '100%', background: brand.profile_completeness < 50 ? '#ff9800' : '#4caf50' }}></div>
            </div>
            <span>{brand.profile_completeness}%</span>
          </div>
        )}
      </div>

      <div style={{ display: 'flex', gap: 20, marginBottom: 20, borderBottom: '1px solid #ddd' }}>
        {tabs.map(tab => (
          <div key={tab.id}
               style={{
                 padding: '10px 20px',
                 cursor: tab.disabled ? 'not-allowed' : 'pointer',
                 borderBottom: activeTab === tab.id ? '3px solid var(--primary-color)' : '3px solid transparent',
                 fontWeight: activeTab === tab.id ? 'bold' : 'normal',
                 color: tab.disabled ? 'var(--text-muted)' : (activeTab === tab.id ? 'var(--primary)' : 'var(--text-main)')
               }}
               onClick={() => !tab.disabled && setActiveTab(tab.id)}>
            {tab.label}
          </div>
        ))}
      </div>

      <div style={{ backgroundColor: 'var(--surface)', padding: '30px', borderRadius: 'var(--radius)', boxShadow: 'var(--shadow)', border: '1px solid var(--border)' }}>
        {activeTab === 'profile' && (
          <BrandProfileTab 
            brand={brand} 
            setBrand={setBrand} 
            isNew={isNew} 
            onSaved={(updatedData) => {
              if (isNew) navigate(`/brands/${updatedData.id}/edit`);
              else loadBrand(id); // Reload to update completeness
            }} 
          />
        )}
        {activeTab === 'knowledge' && !isNew && <BrandKnowledgeTab brandId={id} />}
        {activeTab === 'examples' && !isNew && <BrandExamplesTab brandId={id} />}
        {activeTab === 'versions' && !isNew && <BrandVersionsTab brandId={id} />}
      </div>
    </div>
  );
};

export default BrandEditor;
