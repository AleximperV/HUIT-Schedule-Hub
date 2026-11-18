function initToast(el) {
    const closeBtn = el.querySelector('.toast-close');
    const dismiss = () => {
        el.classList.add('hiding');
        setTimeout(() => el.remove(), 260);
    };
    if (closeBtn) {
        closeBtn.addEventListener('click', dismiss);
    }
    setTimeout(dismiss, 4500);
}

function showToast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `<div class="toast-body">${escapeHtml(message)}</div><button type="button" class="toast-close" aria-label="Đóng">&times;</button>`;
    container.appendChild(toast);
    initToast(toast);
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('#toast-container .toast').forEach(initToast);
    const btnRecommend = document.getElementById('btn-recommend');
    const recommendBox = document.getElementById('recommend-results');

    if (btnRecommend && recommendBox) {
        const fetchRecommendations = async () => {
            const subject = document.getElementById('subject_name')?.value || '';
            const branchId = document.getElementById('branch_id')?.value || '';
            const day = document.getElementById('day_of_week')?.value || '';
            const slot = document.getElementById('shift_or_periods')?.value || '';
            const excludeId = document.getElementById('schedule_id')?.value || '';

            if (!branchId || !day || !slot) {
                recommendBox.innerHTML = '<div class="alert alert-warning">Vui lòng chọn Cơ sở, Thứ và Buổi/Tiết trước khi gợi ý giảng viên.</div>';
                return;
            }

            recommendBox.innerHTML = '<p style="color:#64748b;">Đang phân tích chuyên môn, lịch trống và tối ưu di chuyển...</p>';

            const params = new URLSearchParams({
                action: 'recommend_teachers',
                subject_name: subject,
                branch_id: branchId,
                day_of_week: day,
                shift_or_periods: slot,
                exclude_id: excludeId
            });

            try {
                const res = await fetch('api.php?' + params.toString());
                const data = await res.json();
                if (!data.ok) {
                    recommendBox.innerHTML = `<div class="alert alert-danger">${data.error || 'Lỗi tải gợi ý.'}</div>`;
                    return;
                }

                if (!data.recommendations.length) {
                    recommendBox.innerHTML = '<p>Chưa có dữ liệu giảng viên.</p>';
                    return;
                }

                recommendBox.innerHTML = data.recommendations.map((item, idx) => {
                    const cls = !item.available ? 'recommend-item conflict' : (idx === 0 && item.score > 0 ? 'recommend-item best' : 'recommend-item');
                    const badges = item.reasons.map(r => `<span class="badge badge-${r.type}">${escapeHtml(r.label)}</span>`).join(' ');
                    const warns = item.warnings.map(w => `<div style="font-size:12px;color:#b45309;margin-top:4px;">&#9888; ${escapeHtml(w.message)}</div>`).join('');
                    const conflicts = item.conflicts.map(c => `<div style="font-size:12px;color:#b91c1c;margin-top:4px;">&#10060; ${escapeHtml(c.message)}</div>`).join('');

                    return `
                        <div class="${cls}">
                            <div>
                                <strong>${escapeHtml(item.full_name)}</strong>
                                <span class="badge badge-secondary">Điểm: ${item.score}</span>
                                <div style="font-size:12px;color:#475569;margin:3px 0;">Chuyên môn: ${escapeHtml(item.expertise)}</div>
                                <div style="display:flex;gap:4px;flex-wrap:wrap;margin-top:4px;">${badges}</div>
                                ${warns}
                                ${conflicts}
                            </div>
                            <div>
                                <button type="button" class="btn btn-sm ${item.available ? 'btn-primary' : 'btn-outline'}"
                                    onclick="selectRecommendedTeacher(${item.teacher_id})" ${!item.available ? 'disabled' : ''}>
                                    ${item.available ? 'Chọn GV' : 'Trùng lịch'}
                                </button>
                            </div>
                        </div>
                    `;
                }).join('');
            } catch (err) {
                recommendBox.innerHTML = '<div class="alert alert-danger">Không thể kết nối API gợi ý.</div>';
            }
        };

        btnRecommend.addEventListener('click', fetchRecommendations);
    }
});

function selectRecommendedTeacher(teacherId) {
    const select = document.getElementById('teacher_id');
    if (select) {
        select.value = String(teacherId);
        select.focus();
    }
}

function fillSlotFromAvailability(teacherId, day, slot, branchId) {
    const tEl = document.getElementById('teacher_id');
    const dEl = document.getElementById('day_of_week');
    const sEl = document.getElementById('shift_or_periods');
    const bEl = document.getElementById('branch_id');
    if (tEl) tEl.value = String(teacherId);
    if (dEl) dEl.value = String(day);
    if (sEl) sEl.value = slot;
    if (bEl && branchId) bEl.value = String(branchId);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
