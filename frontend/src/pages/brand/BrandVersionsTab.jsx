import React, { useState, useEffect } from 'react';
import { fetchBrandVersions, restoreBrandVersion } from '../../api/brands';

const BrandVersionsTab = ({ brandId }) => {
  const [versions, setVersions] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadVersions();
  }, [brandId]);

  const loadVersions = async () => {
    setLoading(true);
    try {
      const res = await fetchBrandVersions(brandId);
      setVersions(res.data);
    } catch (err) {
      alert('Không tải được lịch sử.');
    } finally {
      setLoading(false);
    }
  };

  const handleRestore = async (version) => {
    if (!window.confirm(`Khôi phục về phiên bản ${version.version_number} (${new Date(version.created_at).toLocaleString()})? Dữ liệu hiện tại sẽ bị thay thế.`)) return;
    try {
      await restoreBrandVersion(brandId, version.id);
      alert('Khôi phục thành công. Vui lòng tải lại trang.');
      window.location.reload();
    } catch (err) {
      alert('Lỗi khôi phục.');
    }
  };

  if (loading) return <div>Đang tải...</div>;

  return (
    <div>
      <h3>Lịch sử phiên bản</h3>
      <p style={{color: '#666'}}>Xem và khôi phục các thay đổi của hồ sơ thương hiệu.</p>

      <div style={{ display: 'flex', flexDirection: 'column', gap: 15 }}>
        {versions.length === 0 ? <p>Chưa có lịch sử.</p> : versions.map((v, idx) => (
          <div key={v.id} style={{ border: '1px solid #eee', padding: 15, borderRadius: 8, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <div>
              <h4 style={{ margin: '0 0 5px 0' }}>Phiên bản {v.version_number} {idx === 0 ? <span style={{fontSize: 12, color: 'green'}}>(Hiện tại)</span> : ''}</h4>
              <p style={{ margin: 0, color: '#666', fontSize: 14 }}>{new Date(v.created_at).toLocaleString()} - {v.change_summary}</p>
            </div>
            {idx !== 0 && (
              <button className="btn-secondary" onClick={() => handleRestore(v)}>Khôi phục</button>
            )}
          </div>
        ))}
      </div>
    </div>
  );
};

export default BrandVersionsTab;
