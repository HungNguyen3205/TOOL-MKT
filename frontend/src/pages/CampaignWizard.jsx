import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';

const CampaignWizard = () => {
  const [currentStep, setCurrentStep] = useState(1);
  const navigate = useNavigate();

  const steps = [
    'Info', 'Brand', 'Content', 'Pages', 'Media', 'Schedule', 'Review', 'Launch'
  ];

  const handleNext = () => {
    if (currentStep < steps.length) {
      setCurrentStep(currentStep + 1);
    }
  };

  const handlePrev = () => {
    if (currentStep > 1) {
      setCurrentStep(currentStep - 1);
    }
  };

  return (
    <div style={{ padding: 'var(--dn-space-4)' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 'var(--dn-space-6)' }}>
        <h2 style={{ margin: 0, fontSize: 'var(--dn-text-2xl)', color: 'var(--dn-text-primary)' }}>Tạo Chiến Dịch Mới</h2>
        <button onClick={() => navigate('/campaigns')} className="dn-btn" style={{ border: '1px solid var(--dn-border-color)' }}>
          Hủy bỏ
        </button>
      </div>

      {/* Progress Indicator */}
      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 'var(--dn-space-8)', position: 'relative' }}>
        <div style={{ position: 'absolute', top: '12px', left: '0', right: '0', height: '2px', backgroundColor: 'var(--dn-border-color)', zIndex: 0 }}></div>
        <div style={{ position: 'absolute', top: '12px', left: '0', width: `${((currentStep - 1) / (steps.length - 1)) * 100}%`, height: '2px', backgroundColor: 'var(--dn-color-primary)', zIndex: 1, transition: 'width var(--dn-transition-normal)' }}></div>
        
        {steps.map((step, index) => {
          const isCompleted = index + 1 < currentStep;
          const isActive = index + 1 === currentStep;
          return (
            <div key={index} style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', zIndex: 2 }}>
              <div style={{ 
                width: '26px', height: '26px', borderRadius: '50%', 
                backgroundColor: isActive || isCompleted ? 'var(--dn-color-primary)' : 'var(--dn-bg-surface)',
                border: isActive || isCompleted ? '2px solid var(--dn-color-primary)' : '2px solid var(--dn-border-color)',
                color: isActive || isCompleted ? '#fff' : 'var(--dn-text-secondary)',
                display: 'flex', justifyContent: 'center', alignItems: 'center',
                fontSize: '12px', fontWeight: 'bold'
              }}>
                {isCompleted ? '✓' : index + 1}
              </div>
              <span style={{ 
                marginTop: 'var(--dn-space-2)', fontSize: 'var(--dn-text-xs)', 
                color: isActive ? 'var(--dn-color-primary)' : 'var(--dn-text-secondary)',
                fontWeight: isActive ? 'bold' : 'normal'
              }}>
                {step}
              </span>
            </div>
          );
        })}
      </div>

      {/* Step Content Area */}
      <div style={{ backgroundColor: 'var(--dn-bg-surface)', padding: 'var(--dn-space-8)', borderRadius: 'var(--dn-radius-lg)', border: '1px solid var(--dn-border-color)', minHeight: '400px' }}>
        
        {currentStep === 1 && (
          <div>
            <h3 style={{ marginTop: 0 }}>Step 1: Campaign Info</h3>
            <div style={{ marginBottom: '15px' }}>
              <label style={{ display: 'block', marginBottom: '5px' }}>Tên chiến dịch</label>
              <input type="text" placeholder="Ví dụ: Chiến dịch tháng 9" className="form-control" style={{ width: '100%', padding: '10px', borderRadius: '4px', border: '1px solid var(--dn-border-color)', backgroundColor: 'var(--dn-bg-app)', color: 'var(--dn-text-primary)' }} />
            </div>
            <div style={{ marginBottom: '15px' }}>
              <label style={{ display: 'block', marginBottom: '5px' }}>Loại chiến dịch</label>
              <select className="form-control" style={{ width: '100%', padding: '10px', borderRadius: '4px', border: '1px solid var(--dn-border-color)', backgroundColor: 'var(--dn-bg-app)', color: 'var(--dn-text-primary)' }}>
                <option value="social_posting">Social Posting (Đăng bài cơ bản)</option>
                <option value="content_rotation">Content Rotation (Xoay vòng nội dung)</option>
                <option value="bulk_posting">Bulk Posting (Đăng hàng loạt)</option>
              </select>
            </div>
            <div style={{ marginBottom: '15px' }}>
              <label style={{ display: 'block', marginBottom: '5px' }}>Mô tả</label>
              <textarea rows="3" placeholder="Mục tiêu của chiến dịch..." className="form-control" style={{ width: '100%', padding: '10px', borderRadius: '4px', border: '1px solid var(--dn-border-color)', backgroundColor: 'var(--dn-bg-app)', color: 'var(--dn-text-primary)' }}></textarea>
            </div>
          </div>
        )}

        {currentStep > 1 && currentStep < 8 && (
          <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', height: '300px', color: 'var(--dn-text-secondary)' }}>
            <h3>Step {currentStep}: {steps[currentStep-1]}</h3>
            <p>Nội dung của bước này đang được xây dựng (Giao diện giữ chỗ).</p>
          </div>
        )}

        {currentStep === 8 && (
          <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', height: '300px', color: 'var(--dn-text-secondary)' }}>
            <h3 style={{ color: 'var(--dn-color-success)' }}>🎉 Sẵn sàng khởi chạy!</h3>
            <p>Kiểm tra lại toàn bộ thông tin và bấm Launch để bắt đầu chiến dịch.</p>
          </div>
        )}

      </div>

      {/* Footer Navigation */}
      <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 'var(--dn-space-6)' }}>
        <button 
          onClick={handlePrev} 
          disabled={currentStep === 1}
          className="dn-btn" 
          style={{ border: '1px solid var(--dn-border-color)', opacity: currentStep === 1 ? 0.5 : 1 }}
        >
          ← Trở lại
        </button>

        {currentStep < steps.length ? (
          <button onClick={handleNext} className="dn-btn dn-btn-primary">
            Tiếp tục →
          </button>
        ) : (
          <button onClick={() => navigate('/campaigns')} className="dn-btn dn-btn-primary" style={{ backgroundColor: 'var(--dn-color-success)' }}>
            🚀 Launch Campaign
          </button>
        )}
      </div>
    </div>
  );
};

export default CampaignWizard;
