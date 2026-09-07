import React from 'react';

const TopHeader = ({ isCollapsed, toggleSidebar }) => {
  return (
    <header className="dn-header">
      <div className="dn-header-left">
        <button 
          onClick={toggleSidebar}
          style={{ background: 'transparent', border: 'none', cursor: 'pointer', fontSize: '1.2rem', color: 'var(--dn-text-secondary)' }}
        >
          ☰
        </button>
        <div style={{ position: 'relative' }}>
          <span style={{ position: 'absolute', left: '10px', top: '50%', transform: 'translateY(-50%)', color: 'var(--dn-text-tertiary)' }}>🔍</span>
          <input 
            type="text" 
            placeholder="Search anything (Ctrl+K)..." 
            style={{ 
              padding: '8px 15px 8px 35px', 
              borderRadius: 'var(--dn-radius-md)', 
              border: '1px solid var(--dn-border-color)', 
              backgroundColor: 'var(--dn-bg-app)',
              color: 'var(--dn-text-primary)',
              width: '250px'
            }} 
          />
        </div>
      </div>
      
      <div className="dn-header-right">
        <button className="dn-btn dn-btn-primary">
          + Quick Create
        </button>
        <div style={{ 
          width: '32px', 
          height: '32px', 
          borderRadius: '50%', 
          backgroundColor: 'var(--dn-color-primary-light)', 
          display: 'flex', 
          alignItems: 'center', 
          justifyContent: 'center',
          color: 'var(--dn-color-primary)',
          fontWeight: 'bold',
          cursor: 'pointer'
        }}>
          A
        </div>
      </div>
    </header>
  );
};

export default TopHeader;
