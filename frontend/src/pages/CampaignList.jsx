import React from 'react';
import { useNavigate } from 'react-router-dom';

const CampaignList = () => {
  const navigate = useNavigate();

  return (
    <div className="dn-campaigns">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 'var(--dn-space-6)' }}>
        <h2 style={{ margin: 0, fontSize: 'var(--dn-text-2xl)', color: 'var(--dn-text-primary)' }}>Chiến Dịch (Campaigns)</h2>
        <button className="dn-btn dn-btn-primary" onClick={() => navigate('/campaigns/new')}>
          + Tạo Chiến Dịch Mới
        </button>
      </div>

      <div style={{ backgroundColor: 'var(--dn-bg-surface)', padding: 'var(--dn-space-8)', borderRadius: 'var(--dn-radius-lg)', border: '1px solid var(--dn-border-color)', textAlign: 'center' }}>
        <div style={{ fontSize: '3rem', marginBottom: 'var(--dn-space-4)' }}>🚀</div>
        <h3 style={{ margin: '0 0 var(--dn-space-2) 0', color: 'var(--dn-text-primary)' }}>Chưa có chiến dịch nào</h3>
        <p style={{ color: 'var(--dn-text-secondary)', marginBottom: 'var(--dn-space-6)' }}>Hãy tạo chiến dịch đầu tiên để tự động hóa quá trình đăng bài lên Facebook.</p>
        <button className="dn-btn dn-btn-primary" onClick={() => navigate('/campaigns/new')}>
          + Tạo Chiến Dịch
        </button>
      </div>
    </div>
  );
};

export default CampaignList;
