// ==================== 全局状态 ====================
const API_BASE = '?action=';
let myPage = 1, aptPage = 1, seaPage = 1, empPage = 1, coursePage = 1, studentPage = 1, orderPage = 1;
let searchTimers = {};
let commResourceId = null;
let commResourceName = '';
let currentBasicTypeCategory = 'course_type';
let currentClassDetailId = null;

// ==================== 初始化 ====================
document.addEventListener('DOMContentLoaded', () => {
    initTreeNav();
    // 默认激活"我的资源"整合面板
    activatePanel('panel-my-resources');
    loadMyResources();
    loadStats();
    populateFilterChannelSelect();
    populateFilterAssignedToSelect();
    populateFilterAssignedDeptSelect();

    // Panel-enroll back button handler
    document.getElementById('btn-enroll-back').addEventListener('click', () => {
        if (currentEnrollMode === 'resource') {
            activatePanel('panel-my-resources');
            highlightLeafByPanel('panel-my-resources');
            loadMyResources();
        } else {
            if (currentEnrollStudentId) {
                viewStudent(currentEnrollStudentId);
            } else {
                activatePanel('panel-students');
                highlightLeafByPanel('panel-students');
            }
        }
    });
});

// ==================== 树状导航 ====================
function initTreeNav() {
    // 父节点点击：展开/折叠，不加载页面（stopPropagation 防止嵌套父节点冒泡）
    document.querySelectorAll('.tree-parent').forEach(parent => {
        parent.addEventListener('click', function(e) {
            e.stopPropagation();
            const node = this.parentElement;
            if (!node.classList.contains('tree-node')) return;
            node.classList.toggle('expanded');
        });
    });

    // 叶子节点点击：切换面板 + 高亮（阻止冒泡以免触发父节点折叠）
    document.querySelectorAll('.tree-leaf[data-panel]').forEach(leaf => {
        leaf.addEventListener('click', function(e) {
            e.stopPropagation();
            const panelId = this.dataset.panel;
            if (!panelId) return;

            // 高亮当前叶子节点
            document.querySelectorAll('.tree-leaf').forEach(l => l.classList.remove('active'));
            document.querySelectorAll('.tree-parent').forEach(p => p.classList.remove('active'));
            this.classList.add('active');

            // 切换面板
            activatePanel(panelId);
            refreshPanel(panelId);
        });
    });
}

function activatePanel(panelId) {
    document.querySelectorAll('.content-panel').forEach(p => p.classList.remove('active'));
    const target = document.getElementById(panelId);
    if (target) target.classList.add('active');
}

function refreshPanel(panelId) {
    switch (panelId) {
        case 'panel-my-resources': loadMyResources(); break;
        case 'panel-appointments': loadAppointments(); break;
        case 'panel-sea-pool': loadSeaPool(); break;
        case 'panel-channel-settings': loadChannelTable(); break;
        case 'panel-intention-level-settings': loadIntentionLevelTable(); break;
        case 'panel-basic-type-settings': loadBasicTypeTable(); initBasicTypeTabs(); break;
        case 'panel-employees': loadEmployees(); break;
        case 'panel-position-settings': loadPositionsTable(); break;
        case 'panel-org': loadOrgTree(); break;
        case 'panel-courses': loadCourses(); break;
        case 'panel-subjects': loadSubjects(); break;
        case 'panel-classes': currentClassDetailId = null; loadClasses(); break;
        case 'panel-classrooms': loadClassrooms(); break;
        case 'panel-students': loadStudents(); break;
        case 'panel-orders': loadOrders(); break;
        case 'panel-attendance': switchAttendanceTab('tab-attendance-operations'); break;
    }
}

// 高亮指定面板对应的树节点（用于外部调用）
function highlightLeafByPanel(panelId) {
    document.querySelectorAll('.tree-leaf').forEach(l => l.classList.remove('active'));
    const leaf = document.querySelector(`.tree-leaf[data-panel="${panelId}"]`);
    if (leaf) leaf.classList.add('active');
}

// ==================== API ====================
async function api(action, data = null, method = null) {
    let url = API_BASE + action;
    const opts = { headers: { 'Content-Type': 'application/json' } };
    if (data) {
        if (method === 'GET') {
            // GET 请求将参数拼接到 URL 上，确保 PHP $_GET 能正确读取
            const params = new URLSearchParams();
            for (const [key, value] of Object.entries(data)) {
                params.append(key, String(value));
            }
            url += '&' + params.toString();
        } else {
            opts.method = method || 'POST';
            opts.body = JSON.stringify(data);
        }
    }
    const res = await fetch(url, opts);
    return res.json();
}

// ==================== 统计 ====================
async function loadStats() {
    const data = await api('get_stats');
    document.querySelectorAll('[id^="stat-my"]').forEach(el => el.textContent = data.my_resources);
    document.querySelectorAll('[id^="stat-sea"]').forEach(el => el.textContent = data.sea_resources);
    document.querySelectorAll('[id^="stat-apt"]').forEach(el => el.textContent = data.appointments);
    document.querySelectorAll('[id^="stat-emp"]').forEach(el => el.textContent = data.employees || 0);
    document.querySelectorAll('[id^="stat-courses"]').forEach(el => el.textContent = data.courses || 0);
}

// ==================== 渠道管理 ====================
async function loadChannels() {
    return await api('list_channels', null, 'GET');
}

async function populateFilterChannelSelect() {
    const channels = await loadChannels();
    const sel = document.getElementById('filter-source-my');
    if (!sel) return;
    sel.innerHTML = '<option value="">全部渠道</option>' +
        channels.map(ch => `<option value="${esc(ch.name)}">${esc(ch.name)}</option>`).join('');
}

async function populateFilterAssignedToSelect() {
    const sel = document.getElementById('filter-assigned-to-my');
    if (!sel) return;
    try {
        const data = await api('get_employees', null, 'GET');
        const employees = data.data || [];
        sel.innerHTML = '<option value="">全部归属人</option>' +
            employees.map(e => `<option value="${esc(e.name)}">${esc(e.name)}</option>`).join('');
    } catch (e) { /* ignore */ }
}

async function populateFilterAssignedDeptSelect() {
    const sel = document.getElementById('filter-assigned-dept-my');
    if (!sel) return;
    try {
        const result = await api('list_organizations', null, 'GET');
        const flat = (result.data && result.data.flat) ? result.data.flat : [];
        sel.innerHTML = '<option value="">全部归属部门</option>' +
            flat.map(org => `<option value="${esc(org.name)}">[${esc(org.type)}] ${esc(org.name)}</option>`).join('');
    } catch (e) { /* ignore */ }
}

async function populateChannelSelect(selectId, selectedValue) {
    const channels = await loadChannels();
    const sel = document.getElementById(selectId);
    if (!sel) return;
    sel.innerHTML = '<option value="">请选择</option>';
    const existingNames = new Set(channels.map(ch => ch.name));
    channels.forEach(ch => {
        const opt = document.createElement('option');
        opt.value = ch.name;
        opt.textContent = ch.name;
        sel.appendChild(opt);
    });
    // 如果当前资源的渠道已被删除，保留该值并标注
    if (selectedValue && !existingNames.has(selectedValue)) {
        const opt = document.createElement('option');
        opt.value = selectedValue;
        opt.textContent = selectedValue + '（已删除）';
        opt.style.color = '#999';
        sel.appendChild(opt);
    }
    if (selectedValue) sel.value = selectedValue;
}

async function loadChannelTable() {
    const channels = await loadChannels();
    const tbody = document.querySelector('#table-channels tbody');
    if (!channels || channels.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;color:#999;padding:30px;">暂无渠道，请添加</td></tr>';
        return;
    }
    tbody.innerHTML = channels.map(ch => `
        <tr>
            <td><span class="editable-channel" data-id="${ch.id}" data-old="${esc(ch.name)}" onclick="startEditChannel(this)">${esc(ch.name)}</span></td>
            <td>${ch.created_at ? ch.created_at.slice(0,16) : ''}</td>
            <td><button class="btn-link-danger" onclick="deleteChannel(${ch.id})">删除</button></td>
        </tr>
    `).join('');
}

function startEditChannel(spanEl) {
    const chId = parseInt(spanEl.dataset.id);
    const oldName = spanEl.dataset.old;
    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'channel-edit-input';
    input.value = oldName;
    input.maxLength = 50;
    spanEl.replaceWith(input);
    input.focus();
    input.select();

    const doSave = async () => {
        const newName = input.value.trim();
        if (!newName) { showToast('渠道名称不能为空', 'error'); loadChannelTable(); return; }
        if (newName === oldName) { loadChannelTable(); return; }
        const result = await api('update_channel', { id: chId, name: newName });
        if (result.error) { showToast(result.error, 'error'); loadChannelTable(); return; }
        const msg = result.updated_resources > 0
            ? `${result.message}，同步更新了 ${result.updated_resources} 条资源`
            : result.message;
        showToast(msg);
        loadChannelTable();
    };

    input.addEventListener('blur', doSave);
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { input.blur(); }
        if (e.key === 'Escape') { loadChannelTable(); }
    });
}

async function addChannel() {
    const input = document.getElementById('channel-name-input');
    const name = input.value.trim();
    if (!name) return showToast('请输入渠道名称', 'error');
    const result = await api('add_channel', { name });
    if (result.error) { showToast(result.error, 'error'); return; }
    showToast(result.message);
    input.value = '';
    loadChannelTable();
}

async function deleteChannel(cid) {
    showCustomConfirm('确定删除该渠道？', async () => {
        const result = await api('delete_channel', { id: cid });
        showToast(result.message);
        loadChannelTable();
    });
}

// ==================== 意向等级管理 ====================
async function loadIntentionLevels() {
    return await api('list_intention_levels', null, 'GET');
}

async function populateIntentionLevelSelect(selectId, selectedValue) {
    const levels = await loadIntentionLevels();
    const sel = document.getElementById(selectId);
    if (!sel) return;
    sel.innerHTML = '<option value="">请选择</option>';
    const existingNames = new Set(levels.map(l => l.name));
    levels.forEach(l => {
        const opt = document.createElement('option');
        opt.value = l.name;
        opt.textContent = l.name;
        sel.appendChild(opt);
    });
    // 如果当前资源的意向等级已被删除，保留该值并标注
    if (selectedValue && !existingNames.has(selectedValue)) {
        const opt = document.createElement('option');
        opt.value = selectedValue;
        opt.textContent = selectedValue + '（已删除）';
        opt.style.color = '#999';
        sel.appendChild(opt);
    }
    if (selectedValue) sel.value = selectedValue;
}

async function loadIntentionLevelTable() {
    const levels = await loadIntentionLevels();
    const tbody = document.querySelector('#table-intention-levels tbody');
    if (!levels || levels.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:#999;padding:30px;">暂无等级，请添加</td></tr>';
        return;
    }
    tbody.innerHTML = levels.map(l => `
        <tr>
            <td><span class="editable-channel" data-id="${l.id}" data-old="${esc(l.name)}" onclick="startEditIntentionLevelName(this)">${esc(l.name)}</span></td>
            <td><span class="editable-channel" data-id="${l.id}" data-field="sort_order" data-old="${l.sort_order}" onclick="startEditIntentionLevelSort(this)">${l.sort_order}</span></td>
            <td>${l.created_at ? l.created_at.slice(0,16) : ''}</td>
            <td><button class="btn-link-danger" onclick="deleteIntentionLevel(${l.id})">删除</button></td>
        </tr>
    `).join('');
}

function startEditIntentionLevelName(spanEl) {
    const lvId = parseInt(spanEl.dataset.id);
    const oldName = spanEl.dataset.old;
    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'channel-edit-input';
    input.value = oldName;
    input.maxLength = 50;
    spanEl.replaceWith(input);
    input.focus();
    input.select();

    const doSave = async () => {
        const newName = input.value.trim();
        if (!newName) { showToast('意向等级名称不能为空', 'error'); loadIntentionLevelTable(); return; }
        if (newName === oldName) { loadIntentionLevelTable(); return; }
        const result = await api('update_intention_level', { id: lvId, name: newName });
        if (result.error) { showToast(result.error, 'error'); loadIntentionLevelTable(); return; }
        const msg = result.updated_resources > 0
            ? `${result.message}，同步更新了 ${result.updated_resources} 条资源`
            : result.message;
        showToast(msg);
        loadIntentionLevelTable();
    };

    input.addEventListener('blur', doSave);
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { input.blur(); }
        if (e.key === 'Escape') { loadIntentionLevelTable(); }
    });
}

function startEditIntentionLevelSort(spanEl) {
    const lvId = parseInt(spanEl.dataset.id);
    const oldVal = spanEl.dataset.old;
    const input = document.createElement('input');
    input.type = 'number';
    input.className = 'channel-edit-input';
    input.value = oldVal;
    input.min = 0;
    input.style.width = '80px';
    spanEl.replaceWith(input);
    input.focus();
    input.select();

    const doSave = async () => {
        const newVal = parseInt(input.value) || 0;
        const levels = await loadIntentionLevels();
        const current = levels.find(l => l.id === lvId);
        if (!current) { loadIntentionLevelTable(); return; }
        if (newVal === parseInt(oldVal)) { loadIntentionLevelTable(); return; }
        const result = await api('update_intention_level', { id: lvId, name: current.name, sort_order: newVal });
        if (result.error) { showToast(result.error, 'error'); loadIntentionLevelTable(); return; }
        showToast('排序号已更新');
        loadIntentionLevelTable();
    };

    input.addEventListener('blur', doSave);
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { input.blur(); }
        if (e.key === 'Escape') { loadIntentionLevelTable(); }
    });
}

async function addIntentionLevel() {
    const nameInput = document.getElementById('intention-name-input');
    const sortInput = document.getElementById('intention-sort-input');
    const name = nameInput.value.trim();
    if (!name) return showToast('请输入意向等级名称', 'error');
    const sortOrder = parseInt(sortInput.value) || 0;
    const result = await api('add_intention_level', { name, sort_order: sortOrder });
    if (result.error) { showToast(result.error, 'error'); return; }
    showToast(result.message);
    nameInput.value = '';
    sortInput.value = '';
    loadIntentionLevelTable();
}

async function deleteIntentionLevel(lid) {
    showCustomConfirm('确定删除该意向等级？已使用该等级的资源将保留原值。', async () => {
        const result = await api('delete_intention_level', { id: lid });
        showToast(result.message);
        loadIntentionLevelTable();
    });
}

// ==================== Toast ====================
function showToast(msg, type = 'success') {
    const toast = document.createElement('div');
    toast.className = 'toast toast-' + type;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 2500);
}

// ==================== 自定义弹窗（替换 alert/confirm/prompt） ====================
function showCustomDialogHTML(html) {
    const existing = document.querySelector('.custom-dialog-overlay');
    if (existing) existing.remove();
    const overlay = document.createElement('div');
    overlay.className = 'custom-dialog-overlay';
    overlay.innerHTML = html;
    document.body.appendChild(overlay);
    void overlay.offsetWidth;
    overlay.classList.add('show');
    return overlay;
}

function showCustomAlert(msg, callback) {
    const html = '<div class="custom-dialog"><div class="custom-dialog-body"><div class="dialog-icon info">&#9432;</div><div class="dialog-message">' + esc(msg) + '</div></div><div class="custom-dialog-footer"><button class="custom-dialog-btn custom-dialog-btn-primary" id="custom-dialog-ok">确定</button></div></div>';
    const overlay = showCustomDialogHTML(html);
    overlay.querySelector('#custom-dialog-ok').addEventListener('click', function() {
        overlay.remove();
        if (callback) callback();
    });
}

function showCustomConfirm(msg, onConfirm, onCancel) {
    const html = '<div class="custom-dialog"><div class="custom-dialog-body"><div class="dialog-icon warn">&#9888;</div><div class="dialog-message">' + esc(msg) + '</div></div><div class="custom-dialog-footer"><button class="custom-dialog-btn custom-dialog-btn-cancel" id="custom-dialog-cancel">取消</button><button class="custom-dialog-btn custom-dialog-btn-primary" id="custom-dialog-ok">确定</button></div></div>';
    const overlay = showCustomDialogHTML(html);
    overlay.querySelector('#custom-dialog-ok').addEventListener('click', function() {
        overlay.remove();
        if (onConfirm) onConfirm();
    });
    overlay.querySelector('#custom-dialog-cancel').addEventListener('click', function() {
        overlay.remove();
        if (onCancel) onCancel();
    });
}

function showCustomPrompt(msg, defaultVal, onConfirm, onCancel) {
    var escVal = esc(defaultVal || '');
    const html = '<div class="custom-dialog"><div class="custom-dialog-body"><div class="dialog-icon info">&#9998;</div><div class="dialog-message">' + esc(msg) + '</div><input type="text" class="custom-dialog-input" id="custom-dialog-input" value="' + escVal + '" placeholder="请输入..."></div><div class="custom-dialog-footer"><button class="custom-dialog-btn custom-dialog-btn-cancel" id="custom-dialog-cancel">取消</button><button class="custom-dialog-btn custom-dialog-btn-primary" id="custom-dialog-ok">确定</button></div></div>';
    const overlay = showCustomDialogHTML(html);
    const input = overlay.querySelector('#custom-dialog-input');
    overlay.querySelector('#custom-dialog-ok').addEventListener('click', function() {
        overlay.remove();
        if (onConfirm) onConfirm(input.value.trim());
    });
    overlay.querySelector('#custom-dialog-cancel').addEventListener('click', function() {
        overlay.remove();
        if (onCancel) onCancel();
    });
    setTimeout(function() { input.focus(); input.select(); }, 100);
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            overlay.remove();
            if (onConfirm) onConfirm(input.value.trim());
        }
    });
}

// ==================== 防抖搜索 ====================
function debounceSearch(tab) {
    clearTimeout(searchTimers[tab]);
    searchTimers[tab] = setTimeout(() => {
        if (tab === 'my') { myPage = 1; loadMyResources(); }
        else if (tab === 'apt') { aptPage = 1; loadAppointments(); }
        else if (tab === 'sea') { seaPage = 1; loadSeaPool(); }
        else if (tab === 'emp') { empPage = 1; loadEmployees(); }
        else if (tab === 'course') { coursePage = 1; loadCourses(); }
        else if (tab === 'student') { studentPage = 1; loadStudents(); }
        else if (tab === 'order') { orderPage = 1; loadOrders(); }
        else if (tab === 'classroom') { loadClassrooms(); }
    }, 400);
}

// ==================== 弹窗 ====================
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
function openModal(id) { document.getElementById(id).classList.add('show'); }

// ==================== 归属人下拉加载 ====================
async function populateAssignedToSelect(selectedValue) {
    const data = await api('get_employees', null, 'GET');
    const employees = data.data || [];
    const sel = document.getElementById('res-assigned');
    if (!sel) return;
    sel.innerHTML = '<option value="">请选择</option>';
    const existingNames = new Set(employees.map(e => e.name));
    employees.forEach(e => {
        const opt = document.createElement('option');
        opt.value = e.name;
        opt.textContent = e.name;
        sel.appendChild(opt);
    });
    if (selectedValue && !existingNames.has(selectedValue)) {
        const opt = document.createElement('option');
        opt.value = selectedValue;
        opt.textContent = selectedValue + '（已离职/删除）';
        opt.style.color = '#999';
        sel.appendChild(opt);
    }
    if (selectedValue) sel.value = selectedValue;
}

// ==================== 我的资源 ====================
async function loadMyResources() {
    const keyword = document.getElementById('search-my').value;
    const name = document.getElementById('filter-name-my').value;
    const phone = document.getElementById('filter-phone-my').value;
    const source = document.getElementById('filter-source-my').value;
    const assignedTo = document.getElementById('filter-assigned-to-my').value;
    const assignedDept = document.getElementById('filter-assigned-dept-my').value;
    const createdStart = document.getElementById('filter-created-start-my').value;
    const createdEnd = document.getElementById('filter-created-end-my').value;
    const followStatus = document.getElementById('filter-follow-status-my').value;
    const params = new URLSearchParams({ page: myPage, page_size: 15, pool_type: '我的资源', keyword });
    if (name) params.set('name', name);
    if (phone) params.set('phone', phone);
    if (source) params.set('source', source);
    if (assignedTo) params.set('assigned_to', assignedTo);
    if (assignedDept) params.set('assigned_dept', assignedDept);
    if (createdStart) params.set('created_start', createdStart);
    if (createdEnd) params.set('created_end', createdEnd);
    if (followStatus) params.set('follow_status', followStatus);
    const res = await fetch(API_BASE + 'get_resources&' + params);
    const data = await res.json();
    renderMyTable(data.data);
    renderPagination('pagination-my', data.total, myPage, 15, (p) => { myPage = p; loadMyResources(); });
}

function renderMyTable(rows) {
    const tbody = document.querySelector('#table-my-resources tbody');
    if (!rows || rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="14" style="text-align:center;padding:60px 20px;">'
            + '<div style="margin-bottom:16px;">'
            + '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#C4B5FD" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;">'
            + '<circle cx="11" cy="11" r="8"/>'
            + '<line x1="21" y1="21" x2="16.65" y2="16.65"/>'
            + '<line x1="8" y1="11" x2="14" y2="11"/>'
            + '</svg></div>'
            + '<div style="font-size:16px;color:#7C3AED;margin-bottom:8px;font-weight:500;">无查询结果</div>'
            + '<div style="font-size:13px;color:#A78BFA;">调整筛选条件后重新搜索</div>'
            + '</td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(r => `
        <tr>
            <td><input type="checkbox" class="cb-my" value="${r.id}"></td>
            <td>${esc(r.name)}</td>
            <td>${esc(r.phone)}</td>
            <td>${esc(r.source)}</td>
            <td>${esc(r.intention_level)}</td>
            <td>${esc(r.assigned_to)}</td>
            <td>${esc(r.assigned_dept || '')}</td>
            <td><span class="follow-status-tag follow-status-${r.follow_status || 'none'}" onclick="event.stopPropagation();toggleFollowStatusEdit(this, ${r.id})" title="点击修改跟进状态">${r.follow_status || '—'}</span></td>
            <td>${r.created_at ? r.created_at.slice(0,16) : ''}</td>
            <td>${esc(r.gender)}</td>
            <td>${esc(r.birth_date)}</td>
            <td>${r.updated_at ? r.updated_at.slice(0,16) : ''}</td>
            <td>${r.converted === '已转化' ? '<span class="tag-converted">已转化</span>' : '<span class="tag-unconverted">未转化</span>'}</td>
            <td>
                <div class="action-btns">
                    <button class="btn-link" onclick="editResource(${r.id})">编辑</button>
                    <button class="btn-link" onclick="goEnrollFromResource(${r.id})">报名</button>
                    <button class="btn-link" onclick="openAppointmentForResource(${r.id},'${esc(r.name)}','${esc(r.phone)}')">预约试听</button>
                    <button class="btn-link" onclick="openCommunication(${r.id},'${esc(r.name)}')">沟通记录</button>
                    <button class="btn-link-danger" onclick="deleteResource(${r.id})">删除</button>
                </div>
            </td>
        </tr>
    `).join('');
    document.getElementById('select-all-my').checked = false;
}

const FOLLOW_STATUS_OPTIONS = ['未沟通','沟通中','已邀约未试听','已试听待转化','已转化—定金','已转化—全款','无效客户'];

function toggleFollowStatusEdit(tagEl, rid) {
    const existing = document.querySelector('.follow-status-dropdown');
    if (existing) {
        if (existing._rid === rid) { existing.remove(); return; }
        existing.remove();
    }
    const dropdown = document.createElement('div');
    dropdown.className = 'follow-status-dropdown';
    dropdown._rid = rid;
    dropdown.innerHTML = FOLLOW_STATUS_OPTIONS.map(s =>
        `<div class="follow-status-dropdown-item follow-status-${s}" data-val="${s}">${s}</div>`
    ).join('') + '<div class="follow-status-dropdown-item follow-status-none" data-val="">清空</div>';
    dropdown.addEventListener('click', async (e) => {
        const item = e.target.closest('.follow-status-dropdown-item');
        if (!item) return;
        const val = item.dataset.val;
        dropdown.remove();
        tagEl.textContent = '...';
        try {
            const res = await fetch(API_BASE + 'update_resource', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: rid, follow_status: val })
            });
            const data = await res.json();
            if (data.message && !data.error) {
                tagEl.textContent = val || '—';
                tagEl.className = 'follow-status-tag follow-status-' + (val || 'none');
            } else {
                tagEl.textContent = data.error || '更新失败';
            }
        } catch (e) {
            tagEl.textContent = '请求失败';
        }
    });
    document.body.appendChild(dropdown);
    const rect = tagEl.getBoundingClientRect();
    dropdown.style.position = 'fixed';
    dropdown.style.top = (rect.bottom + 4) + 'px';
    dropdown.style.left = rect.left + 'px';
    const closeOnOutside = (e) => {
        if (!dropdown.contains(e.target) && e.target !== tagEl) {
            dropdown.remove();
            document.removeEventListener('click', closeOnOutside);
        }
    };
    setTimeout(() => document.addEventListener('click', closeOnOutside), 0);
}

function showAddModal() {
    document.getElementById('modal-resource-title').textContent = '新增资源';
    document.getElementById('edit-rid').value = '';
    ['res-name','res-phone'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('res-gender').value = '';
    document.getElementById('res-birth-date').value = '';
    document.getElementById('res-follow-status').value = '';
    populateChannelSelect('res-source', '');
    populateIntentionLevelSelect('res-intention', '');
    populateAssignedToSelect('');
    openModal('modal-resource');
}

async function editResource(rid) {
    const res = await fetch(API_BASE + 'get_resources&page=1&page_size=500&pool_type=我的资源');
    const all = await res.json();
    const item = (all.data || []).find(r => r.id === rid);
    if (!item) return showToast('未找到该资源', 'error');
    document.getElementById('modal-resource-title').textContent = '编辑资源';
    document.getElementById('edit-rid').value = item.id;
    document.getElementById('res-name').value = item.name;
    document.getElementById('res-phone').value = item.phone;
    document.getElementById('res-gender').value = item.gender || '';
    document.getElementById('res-birth-date').value = item.birth_date || '';
    document.getElementById('res-follow-status').value = item.follow_status || '';
    await populateAssignedToSelect(item.assigned_to);
    await populateChannelSelect('res-source', item.source);
    await populateIntentionLevelSelect('res-intention', item.intention_level);
    openModal('modal-resource');
}

async function saveResource() {
    const rid = document.getElementById('edit-rid').value;
    const data = {
        id: rid ? parseInt(rid) : 0,
        name: document.getElementById('res-name').value.trim(),
        phone: document.getElementById('res-phone').value.trim(),
        source: document.getElementById('res-source').value,
        intention_level: document.getElementById('res-intention').value,
        gender: document.getElementById('res-gender').value,
        birth_date: document.getElementById('res-birth-date').value,
        follow_status: document.getElementById('res-follow-status').value,
        assigned_to: document.getElementById('res-assigned').value.trim(),
        pool_type: '我的资源'
    };
    if (!data.name) return showToast('姓名不能为空', 'error');
    if (!data.phone) return showToast('手机号不能为空', 'error');
    const action = rid ? 'update_resource' : 'add_resource';
    const result = await api(action, data);
    if (result.error) {
        showToast(result.error, 'error');
        // 手机号重复时高亮手机号输入框
        if (result.error.indexOf('手机号已存在') !== -1) {
            const phoneInput = document.getElementById('res-phone');
            if (phoneInput) {
                phoneInput.style.borderColor = '#e74c3c';
                phoneInput.style.backgroundColor = '#fff5f5';
                phoneInput.focus();
                setTimeout(() => { phoneInput.style.borderColor = ''; phoneInput.style.backgroundColor = ''; }, 3000);
            }
        }
        return;
    }
    showToast(result.message);
    closeModal('modal-resource');
    loadMyResources();
    loadStats();
}

async function deleteResource(rid) {
    showCustomConfirm('确定删除该资源？相关预约和沟通记录将一并删除。', async () => {
        const result = await api('delete_resource', { id: rid });
        showToast(result.message);
        loadMyResources();
        loadStats();
    });
}

// ==================== 内联：新增资源 ====================
async function saveInlineResource() {
    const data = {
        id: 0,
        name: document.getElementById('inline-res-name').value.trim(),
        phone: document.getElementById('inline-res-phone').value.trim(),
        source: document.getElementById('inline-res-source').value,
        intention_level: document.getElementById('inline-res-intention').value,
        follow_status: document.getElementById('inline-res-follow-status')?.value ?? '',
        assigned_to: document.getElementById('inline-res-assigned').value.trim(),
        pool_type: '我的资源'
    };
    if (!data.name) return showToast('姓名不能为空', 'error');
    if (!data.phone) return showToast('手机号不能为空', 'error');
    const result = await api('add_resource', data);
    showToast(result.message);
    // 清空表单
    ['inline-res-name','inline-res-phone','inline-res-assigned'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('inline-res-source').value = '';
    document.getElementById('inline-res-intention').value = '';
    loadStats();
}

// ==================== 批量分配 ====================
function getSelectedIds(cls) {
    return [...document.querySelectorAll('.' + cls + ':checked')].map(cb => parseInt(cb.value));
}

function toggleSelectAll(tab) {
    const cls = tab === 'my' ? 'cb-my' : tab === 'sea' ? 'cb-sea' : 'cb-emp';
    const checked = document.getElementById('select-all-' + tab).checked;
    document.querySelectorAll('.' + cls).forEach(cb => cb.checked = checked);
}

// ==================== 批量分配（左树右表） ====================
let baAllEmployees = [];       // 所有员工
let baSelectedNames = [];      // 选中的员工姓名
let baOrgTreeData = null;      // 组织树数据
let baOrgFlatData = null;      // 组织扁平数据
let baDeptCountMap = {};       // 部门 -> 员工数量映射
let baSelectedDept = null;     // 当前选中的部门名称

async function batchAssign() {
    const ids = getSelectedIds('cb-my');
    if (ids.length === 0) return showToast('请先勾选资源', 'error');

    try {
        // 加载所有员工和组织树
        const [empResult, orgResult] = await Promise.all([
            api('get_employees', { page_size: 9999 }, 'GET'),
            api('list_organizations', {})
        ]);

        const employees = empResult.data || [];
        if (employees.length === 0) return showToast('暂无可分配的员工，请先在员工名册中添加', 'error');

        baAllEmployees = employees;
        baSelectedNames = [];
        baSelectedDept = null;
        baOrgTreeData = orgResult.data.tree || [];
        baOrgFlatData = orgResult.data.flat || [];

        // 计算每部门员工数
        baDeptCountMap = {};
        employees.forEach(e => {
            const dept = e.department || '';
            if (dept) baDeptCountMap[dept] = (baDeptCountMap[dept] || 0) + 1;
        });

        // 打开弹窗
        document.getElementById('modal-batch-assign').classList.add('show');
        document.getElementById('ba-search-input').value = '';

        // 渲染组织树和全部员工列表
        renderAssignOrgTree();
        renderEmployeeListInAssign(employees);
    } catch (err) {
        console.error('批量分配弹窗加载失败:', err);
        showToast('加载失败，请检查网络后重试', 'error');
    }
}

function renderAssignOrgTree() {
    const wrap = document.getElementById('ba-tree-wrap');
    if (!baOrgTreeData || baOrgTreeData.length === 0) {
        wrap.innerHTML = '<div style="text-align:center;padding:24px;color:#999;">暂无组织数据</div>';
        return;
    }

    let html = '';
    baOrgTreeData.forEach(root => { html += buildAssignOrgNode(root, 0); });
    wrap.innerHTML = html;

    // 绑定展开/折叠事件
    wrap.querySelectorAll('.ba-node-toggle').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const node = this.closest('.ba-tree-node');
            node.classList.toggle('expanded');
            const icon = this.querySelector('.ba-toggle-icon');
            icon.textContent = node.classList.contains('expanded') ? '▼' : '▶';
        });
    });

    // 绑定选中事件
    wrap.querySelectorAll('.ba-node-label').forEach(label => {
        label.addEventListener('click', function(e) {
            e.stopPropagation();
            const node = this.closest('.ba-tree-node');
            const deptName = node.dataset.dept;
            // 切换选中
            wrap.querySelectorAll('.ba-tree-node').forEach(n => n.classList.remove('selected'));
            node.classList.add('selected');
            baSelectedDept = deptName;

            // 筛选员工
            const filtered = baAllEmployees.filter(e => (e.department || '') === deptName);
            renderEmployeeListInAssign(filtered);
        });
    });
}

function buildAssignOrgNode(node, depth) {
    const hasChildren = node.children && node.children.length > 0;
    const deptCount = baDeptCountMap[node.name] || 0;
    const indent = depth * 20;

    let html = '<div class="ba-tree-node expanded" data-dept="' + esc(node.name) + '" style="padding-left:' + indent + 'px">';
    html += '<div class="ba-node-row">';
    if (hasChildren) {
        html += '<span class="ba-node-toggle"><span class="ba-toggle-icon">▼</span></span>';
    } else {
        html += '<span class="ba-node-toggle ba-node-toggle-placeholder"></span>';
    }
    html += '<span class="ba-node-label">' + esc(node.name) + '</span>';
    html += '<span class="ba-node-count">(' + deptCount + '人)</span>';
    html += '</div>';

    if (hasChildren) {
        html += '<div class="ba-node-children">';
        node.children.forEach(function(child) { html += buildAssignOrgNode(child, depth + 1); });
        html += '</div>';
    }

    html += '</div>';
    return html;
}

function searchEmployeesInAssign() {
    const keyword = (document.getElementById('ba-search-input').value || '').trim().toLowerCase();
    let filtered = baAllEmployees;

    // 如果选中了部门，先按部门过滤
    if (baSelectedDept) {
        filtered = filtered.filter(function(e) { return (e.department || '') === baSelectedDept; });
    }

    // 按关键词搜索
    if (keyword) {
        filtered = filtered.filter(function(e) {
            return (e.name || '').toLowerCase().includes(keyword) ||
                   (e.phone || '').toLowerCase().includes(keyword);
        });
    }

    renderEmployeeListInAssign(filtered);
}

function renderEmployeeListInAssign(employees) {
    const wrap = document.getElementById('ba-employee-list');
    const selectAll = document.getElementById('ba-select-all');
    const countSpan = document.getElementById('ba-selected-count');

    if (!employees || employees.length === 0) {
        wrap.innerHTML = '<div style="text-align:center;padding:30px;color:#999;">暂无员工</div>';
        selectAll.checked = false;
        countSpan.textContent = '已选 0 人';
        return;
    }

    // 保持已选中的员工（在当前列表中）
    const currentNames = new Set(employees.map(function(e) { return e.name; }));
    // 全局选中但不在当前列表的名称保留

    var html = '';
    employees.forEach(function(e) {
        var checked = baSelectedNames.indexOf(e.name) >= 0 ? ' checked' : '';
        var jsSafeName = (e.name || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
        html += '<div class="ba-employee-item' + (checked ? ' selected' : '') + '" data-name="' + esc(e.name) + '">';
        html += '<input type="checkbox"' + checked + ' onchange="toggleAssignEmployee(\'' + jsSafeName + '\', this.checked)">';
        html += '<span class="ba-emp-name">' + esc(e.name) + '</span>';
        html += '<span class="ba-emp-phone">' + esc(e.phone || '') + '</span>';
        html += '</div>';
    });
    wrap.innerHTML = html;

    // 更新全选状态
    var allInListChecked = employees.every(function(e) { return baSelectedNames.indexOf(e.name) >= 0; });
    selectAll.checked = allInListChecked && employees.length > 0;
    countSpan.textContent = '已选 ' + baSelectedNames.length + ' 人';
}

function toggleSelectAllAssign() {
    var selectAll = document.getElementById('ba-select-all');
    var checked = selectAll.checked;

    // 获取当前可见的员工列表
    var items = document.querySelectorAll('#ba-employee-list .ba-employee-item');
    items.forEach(function(item) {
        var name = item.dataset.name;
        var idx = baSelectedNames.indexOf(name);
        if (checked && idx < 0) {
            baSelectedNames.push(name);
        } else if (!checked && idx >= 0) {
            baSelectedNames.splice(idx, 1);
        }
        // 更新 checkbox
        var cb = item.querySelector('input[type="checkbox"]');
        if (cb) cb.checked = checked;
        if (checked) {
            item.classList.add('selected');
        } else {
            item.classList.remove('selected');
        }
    });

    document.getElementById('ba-selected-count').textContent = '已选 ' + baSelectedNames.length + ' 人';
}

function toggleAssignEmployee(name, checked) {
    if (checked) {
        if (baSelectedNames.indexOf(name) < 0) baSelectedNames.push(name);
    } else {
        var idx = baSelectedNames.indexOf(name);
        if (idx >= 0) baSelectedNames.splice(idx, 1);
    }

    // 更新行样式
    var item = document.querySelector('#ba-employee-list .ba-employee-item[data-name="' + name.replace(/'/g, "\\'") + '"]');
    if (item) {
        if (checked) item.classList.add('selected');
        else item.classList.remove('selected');
    }

    // 更新全选状态和计数
    var items = document.querySelectorAll('#ba-employee-list .ba-employee-item');
    var allChecked = items.length > 0 && Array.from(items).every(function(it) {
        return baSelectedNames.indexOf(it.dataset.name) >= 0;
    });
    document.getElementById('ba-select-all').checked = allChecked;
    document.getElementById('ba-selected-count').textContent = '已选 ' + baSelectedNames.length + ' 人';
}

async function confirmBatchAssign() {
    const ids = getSelectedIds('cb-my');
    if (ids.length === 0) {
        closeModal('modal-batch-assign');
        return showToast('资源列表为空', 'error');
    }
    if (baSelectedNames.length === 0) {
        return showToast('请至少选择一个归属人', 'error');
    }

    var result = await api('batch_assign', { ids: ids, assigned_to: baSelectedNames });
    if (result.error) {
        showToast(result.error, 'error');
    } else {
        showToast(result.message);
        closeModal('modal-batch-assign');
        loadMyResources();
    }
}

async function batchMoveToSea() {
    const ids = getSelectedIds('cb-my');
    if (ids.length === 0) return showToast('请先勾选资源', 'error');
    showCustomConfirm(`确定将选中的 ${ids.length} 条资源移入公海？`, async () => {
        const result = await api('batch_pool', { ids, pool_type: '资源公海' });
        showToast(result.message);
        loadMyResources();
        loadStats();
    });
}

// ==================== 批量编辑（整合面板） ====================
async function batchEdit() {
    const ids = getSelectedIds('cb-my');
    if (ids.length === 0) return showToast('请先勾选一条资源进行编辑', 'error');
    if (ids.length > 1) return showToast('每次只能编辑一条资源，请只勾选一项', 'error');
    editResource(ids[0]);
}

// ==================== 沟通记录（整合面板按钮） ====================
async function openBatchCommunication() {
    const ids = getSelectedIds('cb-my');
    if (ids.length === 0) return showToast('请先勾选一条资源', 'error');
    if (ids.length > 1) return showToast('每次只能为一条资源添加沟通记录，请只勾选一项', 'error');
    const rid = ids[0];
    // 获取资源名称
    const res = await fetch(API_BASE + 'get_resources&page=1&page_size=500&pool_type=我的资源');
    const all = await res.json();
    const item = (all.data || []).find(r => r.id === rid);
    if (!item) return showToast('未找到该资源', 'error');
    openCommunication(rid, item.name);
}

// ==================== 批量导入（弹窗） ====================
function showBatchImportModal() {
    document.getElementById('batch-import-file').value = '';
    document.getElementById('batch-import-pool').value = '我的资源';
    const resultDiv = document.getElementById('batch-import-result');
    resultDiv.style.display = 'none';
    resultDiv.innerHTML = '';
    openModal('modal-batch-import');
}

async function doBatchImport() {
    const fileInput = document.getElementById('batch-import-file');
    const file = fileInput.files[0];

    if (!file) return showToast('请选择 Excel 文件', 'error');

    const ext = file.name.split('.').pop().toLowerCase();
    if (ext !== 'xlsx' && ext !== 'xls') {
        return showToast('仅支持 .xlsx 或 .xls 格式的 Excel 文件', 'error');
    }

    if (file.size > 10 * 1024 * 1024) {
        return showToast('文件大小不能超过 10MB', 'error');
    }

    const poolType = document.getElementById('batch-import-pool').value;
    const formData = new FormData();
    formData.append('file', file);
    formData.append('pool_type', poolType);

    const btn = document.querySelector('#modal-batch-import .btn-primary');
    btn.disabled = true;
    btn.textContent = '导入中...';

    try {
        const res = await fetch(API_BASE + 'batch_import', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();

        if (result.error) {
            showToast(result.error, 'error');
        } else {
            // 展示导入结果
            const resultDiv = document.getElementById('batch-import-result');
            resultDiv.style.display = 'block';
            let html = '<div class="import-result">';
            html += '<p style="font-weight:bold;margin-bottom:8px;">' + esc(result.message) + '</p>';
            if (result.failures && result.failures.length > 0) {
                html += '<div style="max-height:200px;overflow-y:auto;font-size:13px;">';
                html += '<p style="margin-bottom:4px;font-weight:bold;color:#c0392b;">失败明细：</p>';
                result.failures.forEach(f => {
                    const isDuplicate = f.reason && f.reason.indexOf('已存在') !== -1;
                    const isMissingField = f.reason && f.reason.indexOf('缺少必填字段') !== -1;
                    const rowStyle = isDuplicate ? 'color:#e74c3c;font-weight:bold;' : (isMissingField ? 'color:#e74c3c;font-weight:bold;' : 'color:#c0392b;');
                    html += '<p style="margin:2px 0;' + rowStyle + '">第 ' + f.row + ' 行：' + esc(f.reason) + '</p>';
                });
                html += '</div>';
            }
            html += '</div>';
            resultDiv.innerHTML = html;
            showToast(result.message);
            fileInput.value = '';
            loadMyResources();
            loadStats();
        }
    } catch (e) {
        showToast('导入请求失败: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = '开始导入';
    }
}

function downloadTemplate() {
    const a = document.createElement('a');
    a.href = API_BASE + 'download_template';
    a.download = '导入模板.xlsx';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

// ==================== 内联：批量导入 ====================
async function doInlineBatchImport() {
    const text = document.getElementById('inline-batch-import-text').value.trim();
    if (!text) return showToast('请输入数据', 'error');
    const poolType = document.getElementById('inline-batch-import-pool').value;
    const lines = text.split('\n').filter(l => l.trim());
    const items = lines.map(line => {
        const parts = line.split(',');
        return {
            name: (parts[0] || '').trim(),
            phone: (parts[1] || '').trim(),
            source: (parts[2] || '').trim(),
            intention_level: (parts[3] || '').trim(),
            assigned_to: (parts[4] || '').trim(),
            gender: (parts[5] || '').trim(),
            birth_date: (parts[6] || '').trim(),
            follow_status: (parts[7] || '').trim()
        };
    }).filter(item => item.name && item.phone);
    if (items.length === 0) return showToast('没有有效数据', 'error');
    // 验证渠道（内联）
    const channels2 = await loadChannels();
    const channelNames2 = new Set(channels2.map(c => c.name));
    for (let i = 0; i < items.length; i++) {
        const src = items[i].source;
        if (src && !channelNames2.has(src)) {
            return showToast(`第 ${i+1} 行渠道"${src}"不在已配置渠道中，请先在渠道设置中添加`, 'error');
        }
    }
    // 验证意向等级（内联）
    const levels2 = await loadIntentionLevels();
    const levelNames2 = new Set(levels2.map(l => l.name));
    for (let i = 0; i < items.length; i++) {
        const lv = items[i].intention_level;
        if (lv && !levelNames2.has(lv)) {
            return showToast(`第 ${i+1} 行意向等级"${lv}"不在已配置等级中，请先在意向等级设置中添加`, 'error');
        }
    }
    const result = await api('batch_import', { items, pool_type: poolType });
    if (result.error) { showToast(result.error, 'error'); return; }
    showToast(result.message);
    // 显示失败明细
    if (result.failures && result.failures.length > 0) {
        const details = result.failures.map(f => `第${f.row}行：${f.reason}`).join('\n');
        showToast(details, 'error');
    }
    document.getElementById('inline-batch-import-text').value = '';
    loadStats();
}

// ==================== 预约试听（弹窗） ====================
async function showAppointmentModal() {
    document.getElementById('modal-appointment-title').textContent = '新增预约试听';
    document.getElementById('edit-aid').value = '';
    document.getElementById('apt-resource-id').value = '';
    ['apt-student-name','apt-phone','apt-time','apt-notes'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('apt-status').value = '已预约';
    await populateCourseTypeSelect('apt-course-type', '');
    const res = await fetch(API_BASE + 'get_resources&page=1&page_size=500&pool_type=我的资源');
    const data = await res.json();
    const sel = document.getElementById('apt-resource-select');
    sel.innerHTML = '<option value="">不关联（手动填写）</option>' + (data.data || []).map(r => `<option value="${r.id}">${esc(r.name)} ${r.phone}</option>`).join('');
    sel.onchange = function() {
        const selected = (data.data || []).find(r => r.id === parseInt(this.value));
        if (selected) {
            document.getElementById('apt-resource-id').value = selected.id;
            if (!document.getElementById('apt-student-name').value) document.getElementById('apt-student-name').value = selected.name;
            if (!document.getElementById('apt-phone').value) document.getElementById('apt-phone').value = selected.phone;
        } else { document.getElementById('apt-resource-id').value = ''; }
    };
    openModal('modal-appointment');
}

async function openAppointmentForResource(rid, name, phone) {
    document.getElementById('modal-appointment-title').textContent = '新增预约试听';
    document.getElementById('edit-aid').value = '';
    document.getElementById('apt-resource-id').value = rid;
    document.getElementById('apt-student-name').value = name;
    document.getElementById('apt-phone').value = phone;
    document.getElementById('apt-time').value = '';
    document.getElementById('apt-notes').value = '';
    document.getElementById('apt-status').value = '已预约';
    await populateCourseTypeSelect('apt-course-type', '');
    document.getElementById('apt-resource-select').innerHTML = `<option value="${rid}" selected>${name} ${phone}</option>`;
    openModal('modal-appointment');
}

async function editAppointment(aid) {
    const res = await fetch(API_BASE + 'get_appointments&page=1&page_size=500');
    const data = await res.json();
    const item = (data.data || []).find(r => r.id === aid);
    if (!item) return showToast('未找到该预约', 'error');
    document.getElementById('modal-appointment-title').textContent = '编辑预约试听';
    document.getElementById('edit-aid').value = item.id;
    document.getElementById('apt-resource-id').value = item.resource_id;
    document.getElementById('apt-student-name').value = item.student_name;
    document.getElementById('apt-phone').value = item.phone;
    document.getElementById('apt-course-type').value = item.course_type;
    document.getElementById('apt-time').value = item.appointment_time ? item.appointment_time.slice(0,16) : '';
    document.getElementById('apt-status').value = item.status;
    document.getElementById('apt-notes').value = item.notes || '';
    document.getElementById('apt-resource-select').innerHTML = `<option value="${item.resource_id}" selected>${esc(item.resource_name)}</option>`;
    openModal('modal-appointment');
}

async function saveAppointment() {
    const aid = document.getElementById('edit-aid').value;
    const data = {
        id: aid ? parseInt(aid) : 0,
        resource_id: parseInt(document.getElementById('apt-resource-id').value) || 0,
        resource_name: document.getElementById('apt-resource-select').selectedOptions[0]?.text || '',
        student_name: document.getElementById('apt-student-name').value.trim(),
        phone: document.getElementById('apt-phone').value.trim(),
        course_type: document.getElementById('apt-course-type').value,
        appointment_time: document.getElementById('apt-time').value,
        status: document.getElementById('apt-status').value,
        notes: document.getElementById('apt-notes').value.trim()
    };
    if (!data.student_name) return showToast('学员姓名不能为空', 'error');
    if (!data.appointment_time) return showToast('预约时间不能为空', 'error');
    const action = aid ? 'update_appointment' : 'add_appointment';
    const result = await api(action, data);
    showToast(result.message);
    closeModal('modal-appointment');
    loadAppointments();
    loadStats();
}

async function deleteAppointment(aid) {
    showCustomConfirm('确定删除该预约记录？', async () => {
        const result = await api('delete_appointment', { id: aid });
        showToast(result.message);
        loadAppointments();
        loadStats();
    });
}

// ==================== 内联：预约试听 ====================
async function loadResourcesIntoSelect(selectId) {
    const sel = document.getElementById(selectId);
    if (!sel) return;
    const res = await fetch(API_BASE + 'get_resources&page=1&page_size=500&pool_type=我的资源');
    const data = await res.json();
    sel.innerHTML = '<option value="">请选择资源</option>' + (data.data || []).map(r => `<option value="${r.id}">${esc(r.name)} ${r.phone}</option>`).join('');
    // 为预约试听的内联 select 绑定 onchange
    if (selectId === 'inline-apt-resource-select') {
        sel.onchange = function() {
            const selected = (data.data || []).find(r => r.id === parseInt(this.value));
            if (selected) {
                document.getElementById('inline-apt-resource-id').value = selected.id;
                if (!document.getElementById('inline-apt-student-name').value) document.getElementById('inline-apt-student-name').value = selected.name;
                if (!document.getElementById('inline-apt-phone').value) document.getElementById('inline-apt-phone').value = selected.phone;
            } else { document.getElementById('inline-apt-resource-id').value = ''; }
        };
    }
}

async function saveInlineAppointment() {
    const data = {
        id: 0,
        resource_id: parseInt(document.getElementById('inline-apt-resource-id').value) || 0,
        resource_name: document.getElementById('inline-apt-resource-select').selectedOptions[0]?.text || '',
        student_name: document.getElementById('inline-apt-student-name').value.trim(),
        phone: document.getElementById('inline-apt-phone').value.trim(),
        course_type: document.getElementById('inline-apt-course-type').value,
        appointment_time: document.getElementById('inline-apt-time').value,
        status: document.getElementById('inline-apt-status').value,
        notes: document.getElementById('inline-apt-notes').value.trim()
    };
    if (!data.student_name) return showToast('学员姓名不能为空', 'error');
    if (!data.appointment_time) return showToast('预约时间不能为空', 'error');
    const result = await api('add_appointment', data);
    showToast(result.message);
    ['inline-apt-student-name','inline-apt-phone','inline-apt-notes'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('inline-apt-course-type').value = '';
    document.getElementById('inline-apt-time').value = '';
    document.getElementById('inline-apt-status').value = '已预约';
    loadStats();
}

// ==================== 资源公海 ====================
async function loadSeaPool() {
    const keyword = document.getElementById('search-sea').value;
    const params = new URLSearchParams({ page: seaPage, page_size: 15, pool_type: '资源公海', keyword });
    const res = await fetch(API_BASE + 'get_resources&' + params);
    const data = await res.json();
    renderSeaTable(data.data);
    renderPagination('pagination-sea', data.total, seaPage, 15, (p) => { seaPage = p; loadSeaPool(); });
}

function renderSeaTable(rows) {
    const tbody = document.querySelector('#table-sea-pool tbody');
    if (!rows || rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:#999;padding:30px;">公海暂无资源</td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(r => `
        <tr>
            <td><input type="checkbox" class="cb-sea" value="${r.id}"></td>
            <td>${esc(r.name)}</td>
            <td>${esc(r.phone)}</td>
            <td>${esc(r.source)}</td>
            <td>${esc(r.intention_level)}</td>
            <td>${esc(r.gender)}</td>
            <td>${esc(r.birth_date)}</td>
            <td>${r.created_at ? r.created_at.slice(0,16) : ''}</td>
            <td><button class="btn-link" onclick="pickFromSea(${r.id})">领取</button></td>
        </tr>
    `).join('');
    document.getElementById('select-all-sea').checked = false;
}

async function pickFromSea(rid) {
    const result = await api('batch_pool', { ids: [rid], pool_type: '我的资源' });
    showToast(result.message);
    loadSeaPool();
    loadStats();
}

async function batchPickFromSea() {
    const ids = getSelectedIds('cb-sea');
    if (ids.length === 0) return showToast('请先勾选资源', 'error');
    const result = await api('batch_pool', { ids, pool_type: '我的资源' });
    showToast(result.message);
    loadSeaPool();
    loadStats();
}

// ==================== 导出资源 ====================
function exportMyResources(poolType) {
    const isMy = poolType === '我的资源';
    const keywordInput = document.getElementById(isMy ? 'search-my' : 'search-sea');
    const keyword = keywordInput ? keywordInput.value.trim() : '';
    const followStatusSelect = isMy ? document.getElementById('filter-follow-status-my') : null;
    const followStatus = followStatusSelect ? followStatusSelect.value : '';

    let url = API_BASE + 'export_resources&pool_type=' + encodeURIComponent(poolType);
    if (keyword) url += '&keyword=' + encodeURIComponent(keyword);
    if (followStatus) url += '&follow_status=' + encodeURIComponent(followStatus);

    const a = document.createElement('a');
    a.href = url;
    a.download = '';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

// ==================== 沟通记录（弹窗） ====================
async function openCommunication(rid, name) {
    commResourceId = rid;
    commResourceName = name;
    document.getElementById('comm-resource-name').textContent = name;
    document.getElementById('comm-content').value = '';
    document.getElementById('comm-type').value = '电话';
    document.getElementById('comm-new-status').value = '已沟通';
    const records = await api('get_communications&resource_id=' + rid);
    const historyDiv = document.getElementById('comm-history');
    if (!records || records.length === 0) {
        historyDiv.innerHTML = '<p style="color:#999;font-size:13px;">暂无沟通记录</p>';
    } else {
        historyDiv.innerHTML = records.map(r => `
            <div class="comm-item">
                <div class="comm-meta">${r.created_at ? r.created_at.slice(0,16) : ''} · ${esc(r.comm_type)}</div>
                <div class="comm-body">${esc(r.content)}</div>
            </div>
        `).join('');
    }
    openModal('modal-communication');
}

async function addCommunication() {
    const content = document.getElementById('comm-content').value.trim();
    if (!content) return showToast('请输入沟通内容', 'error');
    const result = await api('add_communication', {
        resource_id: commResourceId,
        resource_name: commResourceName,
        content,
        comm_type: document.getElementById('comm-type').value,
        new_status: document.getElementById('comm-new-status').value
    });
    showToast(result.message);
    closeModal('modal-communication');
    loadMyResources();
    loadStats();
}

// ==================== 内联：沟通记录 ====================
async function onInlineCommResourceChange() {
    const sel = document.getElementById('inline-comm-resource-select');
    const rid = parseInt(sel.value) || 0;
    document.getElementById('inline-comm-resource-id').value = rid;
    if (!rid) {
        document.getElementById('inline-comm-history').innerHTML = '<p style="color:#999;font-size:13px;">请先选择资源</p>';
        return;
    }
    const records = await api('get_communications&resource_id=' + rid);
    const historyDiv = document.getElementById('inline-comm-history');
    if (!records || records.length === 0) {
        historyDiv.innerHTML = '<p style="color:#999;font-size:13px;">暂无沟通记录</p>';
    } else {
        historyDiv.innerHTML = records.map(r => `
            <div class="comm-item">
                <div class="comm-meta">${r.created_at ? r.created_at.slice(0,16) : ''} · ${esc(r.comm_type)}</div>
                <div class="comm-body">${esc(r.content)}</div>
            </div>
        `).join('');
    }
}

async function addInlineCommunication() {
    const rid = parseInt(document.getElementById('inline-comm-resource-id').value) || 0;
    if (!rid) return showToast('请先选择资源', 'error');
    const content = document.getElementById('inline-comm-content').value.trim();
    if (!content) return showToast('请输入沟通内容', 'error');
    const resourceName = document.getElementById('inline-comm-resource-select').selectedOptions[0]?.text || '';
    const result = await api('add_communication', {
        resource_id: rid,
        resource_name: resourceName,
        content,
        comm_type: document.getElementById('inline-comm-type').value,
        new_status: document.getElementById('inline-comm-new-status').value
    });
    showToast(result.message);
    document.getElementById('inline-comm-content').value = '';
    document.getElementById('inline-comm-type').value = '电话';
    document.getElementById('inline-comm-new-status').value = '已沟通';
    // 刷新沟通记录
    onInlineCommResourceChange();
    loadStats();
}

// ==================== 预约试听名单 ====================
async function loadAppointments() {
    const keyword = document.getElementById('search-apt').value;
    const status = document.getElementById('filter-status-apt').value;
    const params = new URLSearchParams({ page: aptPage, page_size: 15, keyword, status });
    const res = await fetch(API_BASE + 'get_appointments&' + params);
    const data = await res.json();
    renderAptTable(data.data);
    renderPagination('pagination-apt', data.total, aptPage, 15, (p) => { aptPage = p; loadAppointments(); });
}

function renderAptTable(rows) {
    const tbody = document.querySelector('#table-appointments tbody');
    if (!rows || rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#999;padding:30px;">暂无预约记录</td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(r => `
        <tr>
            <td>${esc(r.student_name)}</td>
            <td>${esc(r.resource_name)}</td>
            <td>${esc(r.phone)}</td>
            <td>${esc(r.course_type)}</td>
            <td>${r.appointment_time ? r.appointment_time.slice(0,16) : ''}</td>
            <td><span class="status-tag status-${r.status}">${r.status}</span></td>
            <td>${esc(r.notes)}</td>
            <td>
                <div class="action-btns">
                    <button class="btn-link" onclick="editAppointment(${r.id})">编辑</button>
                    <button class="btn-link-danger" onclick="deleteAppointment(${r.id})">删除</button>
                </div>
            </td>
        </tr>
    `).join('');
}

// ==================== 分页 ====================
function renderPagination(containerId, total, page, pageSize, callback) {
    const totalPages = Math.ceil(total / pageSize);
    const container = document.getElementById(containerId);
    if (totalPages <= 1) { container.innerHTML = ''; return; }
    let html = `<button ${page === 1 ? 'disabled' : ''} data-page="${page-1}">上一页</button>`;
    const maxShow = 5;
    let start = Math.max(1, page - Math.floor(maxShow / 2));
    let end = Math.min(totalPages, start + maxShow - 1);
    if (end - start + 1 < maxShow) start = Math.max(1, end - maxShow + 1);
    if (start > 1) { html += `<button data-page="1">1</button>`; if (start > 2) html += '<span>...</span>'; }
    for (let i = start; i <= end; i++) {
        html += `<button class="${i === page ? 'active' : ''}" data-page="${i}">${i}</button>`;
    }
    if (end < totalPages) { if (end < totalPages - 1) html += '<span>...</span>'; html += `<button data-page="${totalPages}">${totalPages}</button>`; }
    html += `<button ${page === totalPages ? 'disabled' : ''} data-page="${page+1}">下一页</button>`;
    html += `<span>共 ${total} 条</span>`;
    container.innerHTML = html;
    container.querySelectorAll('button:not([disabled])').forEach(btn => {
        btn.addEventListener('click', () => callback(parseInt(btn.dataset.page)));
    });
}

// ==================== 基础类型设置 ====================
async function loadBasicTypes(category) {
    return await api('list_basic_types&category=' + encodeURIComponent(category), null, 'GET');
}

function initBasicTypeTabs() {
    document.querySelectorAll('.bt-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.bt-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            currentBasicTypeCategory = this.dataset.cat;
            loadBasicTypeTable();
        });
    });
}

async function loadBasicTypeTable() {
    const types = await loadBasicTypes(currentBasicTypeCategory);
    const tbody = document.querySelector('#table-basic-types tbody');
    if (!types || types.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:#999;padding:30px;">暂无数据，请添加</td></tr>';
        return;
    }
    tbody.innerHTML = types.map(t => `
        <tr>
            <td><span class="editable-channel" data-id="${t.id}" data-old="${esc(t.name)}" onclick="startEditBasicTypeName(this)">${esc(t.name)}</span></td>
            <td><span class="editable-channel" data-id="${t.id}" data-old="${t.sort_order}" onclick="startEditBasicTypeSort(this)">${t.sort_order}</span></td>
            <td>${t.created_at ? t.created_at.slice(0,16) : ''}</td>
            <td><button class="btn-link-danger" onclick="deleteBasicType(${t.id})">删除</button></td>
        </tr>
    `).join('');
}

function startEditBasicTypeName(spanEl) {
    const btId = parseInt(spanEl.dataset.id);
    const oldName = spanEl.dataset.old;
    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'channel-edit-input';
    input.value = oldName;
    input.maxLength = 50;
    spanEl.replaceWith(input);
    input.focus();
    input.select();

    const doSave = async () => {
        const newName = input.value.trim();
        if (!newName) { showToast('名称不能为空', 'error'); loadBasicTypeTable(); return; }
        if (newName === oldName) { loadBasicTypeTable(); return; }
        const result = await api('update_basic_type', { id: btId, name: newName });
        if (result.error) { showToast(result.error, 'error'); loadBasicTypeTable(); return; }
        showToast(result.message);
        loadBasicTypeTable();
    };

    input.addEventListener('blur', doSave);
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { input.blur(); }
        if (e.key === 'Escape') { loadBasicTypeTable(); }
    });
}

function startEditBasicTypeSort(spanEl) {
    const btId = parseInt(spanEl.dataset.id);
    const oldVal = spanEl.dataset.old;
    const input = document.createElement('input');
    input.type = 'number';
    input.className = 'channel-edit-input';
    input.value = oldVal;
    input.min = 0;
    input.style.width = '80px';
    spanEl.replaceWith(input);
    input.focus();
    input.select();

    const doSave = async () => {
        const newVal = parseInt(input.value) || 0;
        if (newVal === parseInt(oldVal)) { loadBasicTypeTable(); return; }
        const result = await api('update_basic_type', { id: btId, sort_order: newVal });
        if (result.error) { showToast(result.error, 'error'); loadBasicTypeTable(); return; }
        showToast('排序号已更新');
        loadBasicTypeTable();
    };

    input.addEventListener('blur', doSave);
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { input.blur(); }
        if (e.key === 'Escape') { loadBasicTypeTable(); }
    });
}

async function addBasicType() {
    const nameInput = document.getElementById('bt-name-input');
    const sortInput = document.getElementById('bt-sort-input');
    const name = nameInput.value.trim();
    if (!name) return showToast('请输入名称', 'error');
    const sortOrder = parseInt(sortInput.value) || 0;
    const result = await api('add_basic_type', { category: currentBasicTypeCategory, name, sort_order: sortOrder });
    if (result.error) { showToast(result.error, 'error'); return; }
    showToast(result.message);
    nameInput.value = '';
    sortInput.value = '';
    loadBasicTypeTable();
}

async function deleteBasicType(btId) {
    showCustomConfirm('确定删除该类型？已使用该类型的数据将保留原值。', async () => {
        const result = await api('delete_basic_type', { id: btId });
        showToast(result.message);
        loadBasicTypeTable();
    });
}

async function populateCourseTypeSelect(selectId, selectedValue) {
    const types = await loadBasicTypes('course_type');
    const sel = document.getElementById(selectId);
    if (!sel) return;
    sel.innerHTML = '<option value="">请选择</option>';
    const existingNames = new Set(types.map(t => t.name));
    types.forEach(t => {
        const opt = document.createElement('option');
        opt.value = t.name;
        opt.textContent = t.name;
        sel.appendChild(opt);
    });
    if (selectedValue && !existingNames.has(selectedValue)) {
        const opt = document.createElement('option');
        opt.value = selectedValue;
        opt.textContent = selectedValue + '（已删除）';
        opt.style.color = '#999';
        sel.appendChild(opt);
    }
    if (selectedValue) sel.value = selectedValue;
}

async function populateCommTypeSelect(selectId, selectedValue) {
    const types = await loadBasicTypes('comm_type');
    const sel = document.getElementById(selectId);
    if (!sel) return;
    sel.innerHTML = '';
    const existingNames = new Set(types.map(t => t.name));
    types.forEach(t => {
        const opt = document.createElement('option');
        opt.value = t.name;
        opt.textContent = t.name;
        sel.appendChild(opt);
    });
    if (selectedValue && !existingNames.has(selectedValue)) {
        const opt = document.createElement('option');
        opt.value = selectedValue;
        opt.textContent = selectedValue + '（已删除）';
        opt.style.color = '#999';
        sel.appendChild(opt);
    }
    if (selectedValue) sel.value = selectedValue;
}

// ==================== 员工管理 ====================
async function loadEmployees() {
    const keyword = document.getElementById('search-emp').value;
    const nameFilter = document.getElementById('filter-emp-name').value;
    const dept = document.getElementById('filter-emp-dept').value;
    const status = document.getElementById('filter-emp-status').value;
    const finalKeyword = keyword || nameFilter;
    const params = new URLSearchParams({ page: empPage, page_size: 15 });
    if (finalKeyword) params.set('keyword', finalKeyword);
    if (dept) params.set('department', dept);
    if (status) params.set('status', status);
    const res = await fetch(API_BASE + 'get_employees&' + params);
    const data = await res.json();
    renderEmpTable(data.data);
    renderPagination('pagination-emp', data.total, empPage, 15, (p) => { empPage = p; loadEmployees(); });
    populateEmpDeptFilter(data.data);
}

function populateEmpDeptFilter(rows) {
    const sel = document.getElementById('filter-emp-dept');
    const currentVal = sel.value;
    const depts = [...new Set((rows || []).map(r => r.department).filter(Boolean))];
    sel.innerHTML = '<option value="">全部部门</option>' +
        depts.map(d => `<option value="${esc(d)}">${esc(d)}</option>`).join('');
    if (depts.includes(currentVal)) sel.value = currentVal;
}

function renderEmpTable(rows) {
    const tbody = document.querySelector('#table-employees tbody');
    if (!rows || rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;color:#999;padding:30px;">暂无员工数据，请点击"新增员工"添加</td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(r => `
        <tr>
            <td><input type="checkbox" class="cb-emp" value="${r.id}"></td>
            <td>${esc(r.name)}</td>
            <td>${esc(r.phone)}</td>
            <td>${esc(r.department)}</td>
            <td>${esc(r.position)}</td>
            <td>${esc(r.entry_date)}</td>
            <td><span class="status-tag ${r.status === '在职' ? 'status-已预约' : 'status-已取消'}">${esc(r.status)}</span></td>
            <td>${esc(r.is_teacher || '否')}</td>
            <td>${r.created_at ? r.created_at.slice(0, 16) : ''}</td>
            <td>
                <div class="action-btns">
                    <button class="btn-link" onclick="editEmployee(${r.id})">编辑</button>
                    <button class="btn-link-danger" onclick="deleteEmployee(${r.id}, '${esc(r.name)}')">删除</button>
                </div>
            </td>
        </tr>
    `).join('');
    document.getElementById('select-all-emp').checked = false;
}

async function populateEmpDeptSelect(selectedValue) {
    const sel = document.getElementById('emp-department');
    if (!sel) return;
    sel.innerHTML = '<option value="">请选择（可不填）</option>';
    try {
        const result = await api('list_organizations', null, 'GET');
        const flat = (result.data && result.data.flat) ? result.data.flat : [];
        flat.forEach(org => {
            const opt = document.createElement('option');
            opt.value = org.name;
            opt.textContent = '[' + org.type + '] ' + org.name;
            sel.appendChild(opt);
        });
        if (selectedValue) sel.value = selectedValue;
    } catch (e) { /* ignore */ }
}

async function populateEmpPositionSelect(selectedValue) {
    const sel = document.getElementById('emp-position');
    if (!sel) return;
    sel.innerHTML = '<option value="">请选择（可不填）</option>';
    try {
        const result = await api('list_positions', null, 'GET');
        const positions = Array.isArray(result) ? result : [];
        positions.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.name;
            opt.textContent = p.name;
            sel.appendChild(opt);
        });
        if (selectedValue) sel.value = selectedValue;
    } catch (e) { /* ignore */ }
}

async function showEmpModal() {
    document.getElementById('edit-eid').value = '';
    document.getElementById('modal-employee-title').textContent = '新增员工';
    document.getElementById('emp-name').value = '';
    document.getElementById('emp-phone').value = '';
    document.getElementById('emp-entry-date').value = '';
    document.getElementById('emp-status').value = '在职';
    document.getElementById('emp-is-teacher').value = '否';
    await populateEmpDeptSelect('');
    await populateEmpPositionSelect('');
    openModal('modal-employee');
}

async function editEmployee(eid) {
    const res = await fetch(API_BASE + 'get_employees&page=1&page_size=1&keyword=');
    const data = await res.json();
    let emp = null;
    if (data.data) emp = data.data.find(e => e.id === eid);
    if (!emp) {
        const res2 = await fetch(API_BASE + 'get_employees&page=1&page_size=' + (eid + 10));
        const data2 = await res2.json();
        if (data2.data) emp = data2.data.find(e => e.id === eid);
    }
    if (!emp) { showToast('未找到该员工', 'error'); return; }
    document.getElementById('edit-eid').value = emp.id;
    document.getElementById('modal-employee-title').textContent = '编辑员工';
    document.getElementById('emp-name').value = emp.name || '';
    document.getElementById('emp-phone').value = emp.phone || '';
    document.getElementById('emp-entry-date').value = emp.entry_date || '';
    document.getElementById('emp-status').value = emp.status || '在职';
    document.getElementById('emp-is-teacher').value = emp.is_teacher || '否';
    await populateEmpDeptSelect(emp.department || '');
    await populateEmpPositionSelect(emp.position || '');
    openModal('modal-employee');
}

async function saveEmployee() {
    const eid = document.getElementById('edit-eid').value;
    const name = document.getElementById('emp-name').value.trim();
    if (!name) return showToast('姓名不能为空', 'error');
    const phone = document.getElementById('emp-phone').value.trim();
    const data = {
        name,
        phone,
        department: document.getElementById('emp-department').value.trim(),
        position: document.getElementById('emp-position').value.trim(),
        entry_date: document.getElementById('emp-entry-date').value,
        status: document.getElementById('emp-status').value,
        is_teacher: document.getElementById('emp-is-teacher').value
    };
    let result;
    if (eid) {
        data.id = parseInt(eid);
        result = await api('update_employee', data);
    } else {
        result = await api('add_employee', data);
    }
    if (result.error) {
        showToast(result.error, 'error');
        // 高亮重复字段输入框
        if (result.error.indexOf('姓名已存在') !== -1) {
            const input = document.getElementById('emp-name');
            if (input) { input.style.borderColor = '#e74c3c'; input.style.backgroundColor = '#fff5f5'; input.focus(); setTimeout(() => { input.style.borderColor = ''; input.style.backgroundColor = ''; }, 3000); }
        }
        if (result.error.indexOf('手机号已存在') !== -1) {
            const input = document.getElementById('emp-phone');
            if (input) { input.style.borderColor = '#e74c3c'; input.style.backgroundColor = '#fff5f5'; input.focus(); setTimeout(() => { input.style.borderColor = ''; input.style.backgroundColor = ''; }, 3000); }
        }
        return;
    }
    showToast(result.message);
    closeModal('modal-employee');
    loadEmployees();
    loadStats();
}

async function deleteEmployee(eid, name) {
    showCustomConfirm(`确定删除员工「${name}」？此操作不可恢复。`, async () => {
        const result = await api('delete_employee', { id: eid });
        showToast(result.message);
        loadEmployees();
        loadStats();
    });
}

// ==================== 员工批量导入 ====================
function showBatchImportEmpModal() {
    document.getElementById('batch-import-emp-file').value = '';
    document.getElementById('batch-import-emp-result').style.display = 'none';
    document.getElementById('batch-import-emp-result').innerHTML = '';
    openModal('modal-batch-import-emp');
}

async function doBatchImportEmp() {
    const fileInput = document.getElementById('batch-import-emp-file');
    const resultDiv = document.getElementById('batch-import-emp-result');
    if (!fileInput.files || !fileInput.files[0]) {
        showToast('请选择文件', 'error');
        return;
    }
    const formData = new FormData();
    formData.append('file', fileInput.files[0]);
    try {
        const res = await fetch(API_BASE + 'batch_import_employees', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.error) {
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = `<div style="color:#e74c3c;">导入失败：${data.error}</div>`;
            showToast(data.error, 'error');
            return;
        }
        resultDiv.style.display = 'block';
        let html = `<div style="color:#27ae60;margin-bottom:8px;">${data.message}</div>`;
        if (data.failures && data.failures.length > 0) {
            html += '<div style="color:#e67e22;font-size:12px;">失败明细：<ul>';
            data.failures.forEach(f => {
                html += `<li>第 ${f.row} 行：${f.reason}</li>`;
            });
            html += '</ul></div>';
        }
        resultDiv.innerHTML = html;
        showToast(data.message);
        loadEmployees();
        loadStats();
    } catch (e) {
        showToast('请求失败：' + e.message, 'error');
    }
}

function downloadEmpTemplate() {
    const csvContent = '姓名,手机号,部门,岗位,入职日期,状态,是否教师\n张三,13800138000,技术部,工程师,2024-01-15,在职,是\n李四,13900139000,市场部,经理,2023-06-01,在职,否';
    const BOM = '\uFEFF';
    const blob = new Blob([BOM + csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = '员工导入模板.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

// ==================== 员工导出 ====================
function exportEmployees() {
    const keyword = document.getElementById('search-emp').value.trim();
    const dept = document.getElementById('filter-emp-dept').value;
    const status = document.getElementById('filter-emp-status').value;
    let url = API_BASE + 'export_employees';
    if (keyword) url += '&keyword=' + encodeURIComponent(keyword);
    if (dept) url += '&department=' + encodeURIComponent(dept);
    if (status) url += '&status=' + encodeURIComponent(status);
    const a = document.createElement('a');
    a.href = url;
    a.download = '';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

// ==================== 岗位管理 ====================
async function loadPositionsTable() {
    try {
        const data = await api('list_positions', null, 'GET');
        const positions = Array.isArray(data) ? data : [];
        renderPositionsTable(positions);
    } catch (e) {
        showToast('加载岗位列表失败：' + e.message, 'error');
    }
}

function renderPositionsTable(positions) {
    const tbody = document.querySelector('#table-positions tbody');
    if (!tbody) return;
    tbody.innerHTML = '';
    positions.forEach((p) => {
        const tr = document.createElement('tr');
        tr.innerHTML =
            '<td><span class="editable" ondblclick="startEditPositionName(this,' + p.id + ')">' + esc(p.name) + '</span></td>' +
            '<td><span class="editable" ondblclick="startEditPositionSort(this,' + p.id + ')">' + p.sort_order + '</span></td>' +
            '<td>' + (p.created_at || '') + '</td>' +
            '<td><button class="btn btn-sm btn-danger" onclick="deletePosition(' + p.id + ')">删除</button></td>';
        tbody.appendChild(tr);
    });
}

function startEditPositionName(spanEl, id) {
    if (spanEl.querySelector('input')) return;
    const oldText = spanEl.textContent;
    spanEl.innerHTML = '<input type="text" value="' + escAttr(oldText) + '" style="width:100%;" maxlength="50">';
    const input = spanEl.querySelector('input');
    input.focus();
    input.select();
    let saved = false;
    const save = () => {
        if (saved) return; saved = true;
        const newVal = input.value.trim();
        spanEl.textContent = newVal;
        api('update_position', { id: id, name: newVal }, 'POST').then(r => {
            if (r && r.error) { spanEl.textContent = oldText; showToast(r.error, 'error'); }
        }).catch(e => { spanEl.textContent = oldText; showToast('保存失败：' + e.message, 'error'); });
    };
    input.addEventListener('blur', save);
    input.addEventListener('keydown', e => { if (e.key === 'Enter') { input.blur(); } if (e.key === 'Escape') { saved = true; spanEl.textContent = oldText; } });
}

function startEditPositionSort(spanEl, id) {
    if (spanEl.querySelector('input')) return;
    const oldText = spanEl.textContent;
    spanEl.innerHTML = '<input type="number" value="' + escAttr(oldText) + '" style="width:80px;" min="0">';
    const input = spanEl.querySelector('input');
    input.focus();
    input.select();
    let saved = false;
    const save = () => {
        if (saved) return; saved = true;
        const newVal = parseInt(input.value) || 0;
        spanEl.textContent = newVal;
        api('update_position', { id: id, sort_order: newVal }, 'POST').then(r => {
            if (r && r.error) { spanEl.textContent = oldText; showToast(r.error, 'error'); }
        }).catch(e => { spanEl.textContent = oldText; showToast('保存失败：' + e.message, 'error'); });
    };
    input.addEventListener('blur', save);
    input.addEventListener('keydown', e => { if (e.key === 'Enter') { input.blur(); } if (e.key === 'Escape') { saved = true; spanEl.textContent = oldText; } });
}

async function addPosition() {
    const nameInput = document.getElementById('position-name-input');
    const sortInput = document.getElementById('position-sort-input');
    const name = nameInput.value.trim();
    if (!name) { showToast('请输入岗位名称', 'error'); return; }
    const sort = parseInt(sortInput.value) || 0;
    try {
        const r = await api('add_position', { name: name, sort_order: sort }, 'POST');
        if (r && r.error) { showToast(r.error, 'error'); return; }
        nameInput.value = '';
        sortInput.value = '';
        showToast('岗位添加成功');
        loadPositionsTable();
    } catch (e) {
        showToast('添加失败：' + e.message, 'error');
    }
}

async function deletePosition(id) {
    if (!confirm('确定删除该岗位吗？')) return;
    try {
        const r = await api('delete_position', { id: id }, 'POST');
        if (r && r.error) { showToast(r.error, 'error'); return; }
        showToast('岗位已删除');
        loadPositionsTable();
    } catch (e) {
        showToast('删除失败：' + e.message, 'error');
    }
}

// ==================== 课程管理 ====================
async function loadCourses() {
    const keyword = document.getElementById('search-course')?.value || '';
    const params = new URLSearchParams({ page: coursePage, page_size: 15, keyword });
    const url = API_BASE + 'list_courses&' + params.toString();
    try {
        const res = await fetch(url);
        const data = await res.json();
        if (data.error) { showToast(data.error, 'error'); return; }
        // 确保校区数据已加载，以便 getCampusDisplayText 将 ID 转为名称
        if (!campusCheckboxData.length) await loadCampusData();
        renderCourseTable(data.data || []);
        renderPagination('pagination-course', data.total, coursePage, 15, (p) => { coursePage = p; loadCourses(); });
        document.getElementById('stat-courses-inline').textContent = data.total;
    } catch (e) {
        showToast('加载课程失败: ' + e.message, 'error');
    }
}

// 校区权限辅助函数
let campusCheckboxData = []; // [{id, name}]

async function loadCampusCheckboxes() {
    const container = document.getElementById('course-campus-checkboxes');
    if (!container) return;
    container.innerHTML = '<span style="color:#999;">加载中...</span>';
    try {
        const res = await fetch(API_BASE + 'list_organizations');
        const data = await res.json();
        const orgs = (data && data.data && data.data.flat) ? data.data.flat : [];
        const campuses = orgs.filter(o => o.type === '校区');
        campusCheckboxData = campuses;
        if (campuses.length === 0) {
            container.innerHTML = '<span style="color:#999;">暂无校区数据，请先在组织管理中创建校区</span>';
            return;
        }
        container.innerHTML = campuses.map(c =>
            `<label style="display:block;margin:2px 0;cursor:pointer;font-size:13px;">
                <input type="checkbox" value="${c.id}" style="margin-right:6px;">${esc(c.name)}
            </label>`
        ).join('');
    } catch (e) {
        container.innerHTML = '<span style="color:#e6a23c;">加载校区失败</span>';
    }
}

// 仅加载校区数据（不渲染 DOM），供列表页使用
async function loadCampusData() {
    try {
        const res = await fetch(API_BASE + 'list_organizations');
        const data = await res.json();
        const orgs = (data && data.data && data.data.flat) ? data.data.flat : [];
        campusCheckboxData = orgs.filter(o => o.type === '校区');
    } catch (e) { /* 静默失败，表格中展示原始 ID */ }
}

function getCampusDisplayText(campusPermissionStr) {
    if (!campusPermissionStr || !campusCheckboxData.length) return '';
    const ids = campusPermissionStr.split(',').map(s => s.trim());
    return campusCheckboxData
        .filter(c => ids.includes(String(c.id)))
        .map(c => c.name)
        .join(', ');
}

function renderCourseTable(rows) {
    const tbody = document.querySelector('#table-courses tbody');
    tbody.innerHTML = '';
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#999;">暂无课程数据</td></tr>';
        return;
    }
    rows.forEach(r => {
        const campusText = getCampusDisplayText(r.campus_permission);
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${r.id}</td>
            <td>${esc(r.name)}</td>
            <td>${esc(r.subject)}</td>
            <td>${campusText || '-'}</td>
            <td>${esc(r.small_package) || '-'}</td>
            <td>${esc(r.toddler) || '-'}</td>
            <td class="action-cell">
                <button class="btn-edit" onclick="showPriceModal(${r.id}, '${esc(r.name).replace(/'/g, "\\'")}', '${esc(r.small_package || '').replace(/'/g, "\\'")}')">设置价格</button>
                <button class="btn-edit" onclick="editCourse(${r.id})">编辑</button>
                <button class="btn-delete" onclick="deleteCourse(${r.id})">删除</button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

async function showCourseModal(id = null) {
    document.getElementById('modal-course-title').textContent = id ? '编辑课程' : '新增课程';
    document.getElementById('edit-cid').value = id || '';

    // 加载学科下拉选项
    await loadSubjectSelectOptions();
    // 加载校区复选框
    await loadCampusCheckboxes();

    if (id) {
        // 从表格中获取数据
        const res = await fetch(API_BASE + 'list_courses&page=1&page_size=200');
        const data = await res.json();
        const course = (data.data || []).find(c => c.id == id);
        if (course) {
            document.getElementById('course-name').value = course.name;
            document.getElementById('course-subject').value = course.subject || '';
            document.getElementById('course-small-package').value = course.small_package || '';
            document.getElementById('course-toddler').value = course.toddler || '';
            // 回填校区复选框
            if (course.campus_permission) {
                const selectedIds = course.campus_permission.split(',').map(s => s.trim());
                document.querySelectorAll('#course-campus-checkboxes input[type="checkbox"]').forEach(cb => {
                    cb.checked = selectedIds.includes(cb.value);
                });
            }
        }
    } else {
        document.getElementById('course-name').value = '';
        document.getElementById('course-subject').value = '';
        document.getElementById('course-small-package').value = '';
        document.getElementById('course-toddler').value = '';
        // 重置校区复选框
        document.querySelectorAll('#course-campus-checkboxes input[type="checkbox"]').forEach(cb => {
            cb.checked = false;
        });
    }
    openModal('modal-course');
}

async function loadSubjectSelectOptions() {
    const select = document.getElementById('course-subject');
    select.innerHTML = '<option value="">请选择学科</option>';
    try {
        const result = await api('list_subjects', null, 'GET');
        if (result && result.tree) {
            result.tree.forEach(parent => {
                const optgroup = document.createElement('optgroup');
                optgroup.label = parent.name;
                // 一级学科本身作为一个选项
                const optParent = document.createElement('option');
                optParent.value = parent.name;
                optParent.textContent = parent.name;
                optgroup.appendChild(optParent);
                // 二级学科
                if (parent.children && parent.children.length > 0) {
                    parent.children.forEach(child => {
                        const opt = document.createElement('option');
                        opt.value = parent.name + ' > ' + child.name;
                        opt.textContent = '  ' + child.name;
                        optgroup.appendChild(opt);
                    });
                }
                select.appendChild(optgroup);
            });
        }
    } catch (e) {
        // 静默处理，下拉保持"请选择学科"
    }
}

async function editCourse(id) {
    showCourseModal(id);
}

async function saveCourse() {
    const cid = document.getElementById('edit-cid').value;
    const name = document.getElementById('course-name').value.trim();
    const subject = document.getElementById('course-subject').value.trim();
    const small_package = document.getElementById('course-small-package').value.trim();
    const toddler = document.getElementById('course-toddler').value.trim();
    // 收集校区权限
    const campusCheckboxes = document.querySelectorAll('#course-campus-checkboxes input[type="checkbox"]:checked');
    const campus_permission = Array.from(campusCheckboxes).map(cb => cb.value).join(',');

    if (!name) { showToast('课程名称不能为空', 'error'); return; }

    const action = cid ? 'update_course' : 'add_course';
    const payload = cid ? { id: parseInt(cid), name, subject, small_package, toddler, campus_permission } : { name, subject, small_package, toddler, campus_permission };
    try {
        const r = await api(action, payload, 'POST');
        if (r && r.error) {
            showToast(r.error, 'error');
            // 名称重复时高亮提示
            if (r.error.includes('已存在')) {
                document.getElementById('course-name').style.borderColor = '#f56c6c';
                setTimeout(() => { document.getElementById('course-name').style.borderColor = ''; }, 2000);
            }
            return;
        }
        closeModal('modal-course');
        showToast(cid ? '课程更新成功' : '课程添加成功');
        loadCourses();
        loadStats();
    } catch (e) {
        showToast('保存失败：' + e.message, 'error');
    }
}

async function deleteCourse(id) {
    if (!confirm('确定删除该课程吗？')) return;
    try {
        const r = await api('delete_course', { id: id }, 'POST');
        if (r && r.error) { showToast(r.error, 'error'); return; }
        showToast('课程已删除');
        loadCourses();
        loadStats();
    } catch (e) {
        showToast('删除失败：' + e.message, 'error');
    }
}

function exportCourses() {
    const keyword = document.getElementById('search-course')?.value || '';
    const params = new URLSearchParams();
    if (keyword) params.append('keyword', keyword);
    const qs = params.toString();
    window.open(API_BASE + 'export_courses' + (qs ? '&' + qs : ''), '_blank');
}

// ==================== 价格管理 ====================
let currentPriceCourseId = 0;
let currentPriceCourseName = '';
let currentPriceCourseSmallPackage = '';
function isSmallPackage(val) { return ['是','1','小课包'].includes(String(val).trim()); }
let currentPlans = [];
let currentSelectedPlanId = 0;
let editingItemId = 0;

function showPriceModal(courseId, courseName, smallPackage) {
    currentPriceCourseId = courseId;
    currentPriceCourseName = courseName;
    currentPriceCourseSmallPackage = smallPackage || '';
    currentSelectedPlanId = 0;
    document.getElementById('modal-price-title').textContent = '设置价格 - ' + courseName;
    document.getElementById('price-plan-list').innerHTML = '<div style="padding:20px;color:#999;">加载中...</div>';
    document.getElementById('price-item-table-body').innerHTML = '';
    document.getElementById('price-plan-name-input').value = '';
    document.getElementById('edit-price-plan-id').value = '';
    openModal('modal-price');
    loadPricePlans(courseId);
}

async function loadPricePlans(courseId) {
    try {
        const data = await api('list_price_plans&course_id=' + courseId, null, 'GET');
        currentPlans = data.data || [];
        renderPlanList();
        if (currentPlans.length > 0 && !currentSelectedPlanId) {
            currentSelectedPlanId = currentPlans[0].id;
        }
        renderItemList();
    } catch (e) {
        showToast('加载价格方案失败: ' + e.message, 'error');
    }
}

function renderPlanList() {
    const container = document.getElementById('price-plan-list');
    if (!currentPlans.length) {
        container.innerHTML = '<div style="padding:20px;color:#999;text-align:center;">暂无价格方案<br>请点击下方按钮新增</div>';
        return;
    }
    container.innerHTML = currentPlans.map(p => {
        const activeClass = p.id === currentSelectedPlanId ? ' active' : '';
        let typeTag = '';
        const pt = p.plan_type || '';
        if (pt === '新报') typeTag = '<span class="tag tag-new-enroll">新报</span>';
        else if (pt === '续费') typeTag = '<span class="tag tag-renewal">续费</span>';
        else if (pt === '小课包') typeTag = '<span class="tag tag-small-pack">小课包</span>';
        return `<div class="price-plan-item${activeClass}" data-plan-id="${p.id}" onclick="selectPlan(${p.id})">
            <span class="price-plan-name">${esc(p.name)}${typeTag}</span>
            <span class="price-plan-actions">
                <button class="btn-link" onclick="event.stopPropagation();editPlan(${p.id})">编辑</button>
                <button class="btn-link-danger" onclick="event.stopPropagation();deletePlan(${p.id})">删除</button>
            </span>
        </div>`;
    }).join('');
}

function selectPlan(planId) {
    currentSelectedPlanId = planId;
    renderPlanList();
    renderItemList();
}

function renderItemList() {
    const tbody = document.getElementById('price-item-table-body');
    const tagEl = document.getElementById('price-plan-type-tag');
    const plan = currentPlans.find(p => p.id === currentSelectedPlanId);
    if (!plan) {
        if (tagEl) tagEl.innerHTML = '';
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#999;">请选择左侧价格方案</td></tr>';
        return;
    }
    // 在报价单列表标题旁展示方案类型标签
    if (tagEl) {
        const pt = plan.plan_type || '';
        if (pt === '新报') tagEl.innerHTML = '<span class="tag tag-new-enroll">新报</span>';
        else if (pt === '续费') tagEl.innerHTML = '<span class="tag tag-renewal">续费</span>';
        else if (pt === '小课包') tagEl.innerHTML = '<span class="tag tag-small-pack">小课包</span>';
        else tagEl.innerHTML = '';
    }
    const items = plan.items || [];
    if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#999;">暂无报价单，请点击下方按钮新增</td></tr>';
        return;
    }
    tbody.innerHTML = items.map(item => `
        <tr>
            <td>${esc(item.name)}</td>
            <td>${item.lesson_count}</td>
            <td>${parseFloat(item.unit_price).toFixed(2)}</td>
            <td>${parseFloat(item.actual_price).toFixed(2)}</td>
            <td>
                <button class="btn-link" onclick="editItem(${item.id})">编辑</button>
                <button class="btn-link-danger" onclick="deleteItem(${item.id})">删除</button>
            </td>
        </tr>
    `).join('');

    // 总计行
    const totalLessons = items.reduce((sum, item) => sum + (parseInt(item.lesson_count) || 0), 0);
    const totalActualPrice = items.reduce((sum, item) => sum + (parseFloat(item.actual_price) || 0), 0);
    tbody.innerHTML += `
        <tr class="price-total-row">
            <td style="font-weight:bold;">总计</td>
            <td style="font-weight:bold;">${totalLessons}</td>
            <td></td>
            <td style="font-weight:bold;">${totalActualPrice.toFixed(2)}</td>
            <td></td>
        </tr>`;
}

function addPlan() {
    document.getElementById('modal-price-plan-title').textContent = '新增价格方案';
    document.getElementById('edit-price-plan-id').value = '';
    document.getElementById('price-plan-name-input').value = '';
    const sel = document.getElementById('price-plan-type-select');
    if (isSmallPackage(currentPriceCourseSmallPackage)) {
        sel.innerHTML = '<option value="小课包">小课包</option>';
        sel.value = '小课包';
        sel.disabled = true;
    } else {
        sel.innerHTML = '<option value="新报">新报</option><option value="续费">续费</option>';
        sel.value = '新报';
        sel.disabled = false;
    }
    openModal('modal-price-plan');
}

function editPlan(planId) {
    const plan = currentPlans.find(p => p.id === planId);
    if (!plan) return;
    document.getElementById('modal-price-plan-title').textContent = '编辑价格方案';
    document.getElementById('edit-price-plan-id').value = plan.id;
    document.getElementById('price-plan-name-input').value = plan.name;
    const sel = document.getElementById('price-plan-type-select');
    if (isSmallPackage(currentPriceCourseSmallPackage)) {
        sel.innerHTML = '<option value="小课包">小课包</option>';
        sel.value = '小课包';
        sel.disabled = true;
    } else {
        sel.innerHTML = '<option value="新报">新报</option><option value="续费">续费</option>';
        sel.value = plan.plan_type || '新报';
        sel.disabled = false;
    }
    openModal('modal-price-plan');
}

async function savePlan() {
    const planId = parseInt(document.getElementById('edit-price-plan-id').value) || 0;
    const planName = document.getElementById('price-plan-name-input').value.trim();
    const planType = document.getElementById('price-plan-type-select').value;
    if (!planName) { showToast('方案名称不能为空', 'error'); return; }

    if (planId > 0) {
        // 编辑已有方案：只更新名称
        const existingPlan = currentPlans.find(p => p.id === planId);
        const items = (existingPlan && existingPlan.items) ? existingPlan.items.map((item, idx) => ({
            name: item.name,
            lesson_count: item.lesson_count,
            unit_price: item.unit_price,
            actual_price: item.actual_price,
            sort_order: idx
        })) : [];

        const r = await api('save_price_plan', {
            course_id: currentPriceCourseId,
            plan_id: planId,
            plan_name: planName,
            plan_type: planType,
            items: items
        }, 'POST');
        if (r && r.error) { showToast(r.error, 'error'); return; }
        closeModal('modal-price-plan');
        showToast('方案名称已更新');
        loadPricePlans(currentPriceCourseId);
    } else {
        // 新增方案：需要至少一个默认报价单
        const planName2 = planName;
        const r = await api('save_price_plan', {
            course_id: currentPriceCourseId,
            plan_name: planName2,
            plan_type: planType,
            items: [{ name: '默认报价单', lesson_count: 1, unit_price: 0, actual_price: 0, sort_order: 0 }]
        }, 'POST');
        if (r && r.error) { showToast(r.error, 'error'); return; }
        closeModal('modal-price-plan');
        showToast('价格方案添加成功');
        loadPricePlans(currentPriceCourseId);
    }
}

async function deletePlan(planId) {
    showCustomConfirm('确定删除该价格方案及其所有报价单？', async () => {
        const r = await api('delete_price_plan', { plan_id: planId }, 'POST');
        if (r && r.error) { showToast(r.error, 'error'); return; }
        showToast('价格方案已删除');
        if (currentSelectedPlanId === planId) {
            currentSelectedPlanId = 0;
        }
        loadPricePlans(currentPriceCourseId);
    });
}

function addItem() {
    if (!currentSelectedPlanId) { showToast('请先选择左侧价格方案', 'error'); return; }
    editingItemId = 0;
    document.getElementById('modal-price-item-title').textContent = '新增报价单';
    document.getElementById('edit-price-item-id').value = '';
    document.getElementById('price-item-name').value = '';
    document.getElementById('price-item-lesson-count').value = '';
    document.getElementById('price-item-unit-price').value = '';
    document.getElementById('price-item-actual-price').value = '';
    openModal('modal-price-item');
}

function editItem(itemId) {
    const plan = currentPlans.find(p => p.id === currentSelectedPlanId);
    if (!plan) return;
    const item = (plan.items || []).find(i => i.id === itemId);
    if (!item) return;
    editingItemId = itemId;
    document.getElementById('modal-price-item-title').textContent = '编辑报价单';
    document.getElementById('edit-price-item-id').value = item.id;
    document.getElementById('price-item-name').value = item.name;
    document.getElementById('price-item-lesson-count').value = item.lesson_count;
    document.getElementById('price-item-unit-price').value = item.unit_price;
    document.getElementById('price-item-actual-price').value = item.actual_price;
    openModal('modal-price-item');
}

async function saveItem() {
    const itemId = parseInt(document.getElementById('edit-price-item-id').value) || 0;
    const name = document.getElementById('price-item-name').value.trim();
    const lessonCount = parseInt(document.getElementById('price-item-lesson-count').value) || 0;
    const unitPrice = parseFloat(document.getElementById('price-item-unit-price').value) || 0;
    const actualPrice = parseFloat(document.getElementById('price-item-actual-price').value) || 0;

    if (!name) { showToast('报价单名称不能为空', 'error'); return; }
    if (lessonCount <= 0) { showToast('课时数量必须大于0', 'error'); return; }

    const plan = currentPlans.find(p => p.id === currentSelectedPlanId);
    if (!plan) { showToast('请先选择价格方案', 'error'); return; }
    let items = (plan.items || []).map((item, idx) => ({
        name: item.name,
        lesson_count: item.lesson_count,
        unit_price: item.unit_price,
        actual_price: item.actual_price,
        sort_order: idx
    }));

    const newItem = {
        name: name,
        lesson_count: lessonCount,
        unit_price: unitPrice,
        actual_price: actualPrice,
        sort_order: items.length
    };

    if (itemId > 0) {
        // 编辑已有报价单：在 items 中替换
        const idx = items.findIndex((_, i) => (plan.items[i] && plan.items[i].id === itemId));
        if (idx >= 0) {
            newItem.sort_order = idx;
            items[idx] = newItem;
        } else {
            items.push(newItem);
        }
    } else {
        items.push(newItem);
    }

    const r = await api('save_price_plan', {
        course_id: currentPriceCourseId,
        plan_id: currentSelectedPlanId,
        plan_name: plan.name,
        plan_type: plan.plan_type || '',
        items: items
    }, 'POST');
    if (r && r.error) { showToast(r.error, 'error'); return; }
    closeModal('modal-price-item');
    showToast(itemId > 0 ? '报价单更新成功' : '报价单添加成功');
    loadPricePlans(currentPriceCourseId);
}

async function deleteItem(itemId) {
    const plan = currentPlans.find(p => p.id === currentSelectedPlanId);
    if (!plan) return;
    const items = (plan.items || []).filter(item => item.id !== itemId);
    if (items.length === 0) {
        showToast('每个价格方案至少保留一个报价单', 'error');
        return;
    }
    showCustomConfirm('确定删除该报价单？', async () => {
        const r = await api('save_price_plan', {
            course_id: currentPriceCourseId,
            plan_id: currentSelectedPlanId,
            plan_name: plan.name,
            plan_type: plan.plan_type || '',
            items: items.map((item, idx) => ({
                name: item.name,
                lesson_count: item.lesson_count,
                unit_price: item.unit_price,
                actual_price: item.actual_price,
                sort_order: idx
            }))
        }, 'POST');
        if (r && r.error) { showToast(r.error, 'error'); return; }
        showToast('报价单已删除');
        loadPricePlans(currentPriceCourseId);
    });
}

// 课时价格变化时自动同步实际支付价格
function onUnitPriceChange() {
    const unitPriceEl = document.getElementById('price-item-unit-price');
    const actualPriceEl = document.getElementById('price-item-actual-price');
    if (unitPriceEl && actualPriceEl) {
        actualPriceEl.value = parseFloat(unitPriceEl.value) || 0;
    }
}

// ==================== 组织管理 ====================
let orgTreeData = [];
let orgFlatData = [];
let selectedOrgId = null;
let currentOrgFilter = '全部';

async function loadOrgTree() {
    try {
        const result = await api('list_organizations', {});
        if (result.error) { showToast(result.error, 'error'); return; }
        orgTreeData = result.data.tree || [];
        orgFlatData = result.data.flat || [];
        renderOrgTree();
    } catch (e) {
        showToast('加载组织树失败: ' + e.message, 'error');
    }
}

function filterOrgTreeByType(nodes, type) {
    return nodes.reduce((acc, node) => {
        const filteredChildren = node.children ? filterOrgTreeByType(node.children, type) : [];
        const matches = node.type === type;
        if (matches || filteredChildren.length > 0) {
            acc.push({ ...node, children: filteredChildren.length > 0 ? filteredChildren : (node.children || []) });
        }
        return acc;
    }, []);
}

function setOrgFilter(type) {
    currentOrgFilter = type;
    renderOrgTree();
}

function renderOrgTree() {
    const wrap = document.getElementById('org-tree-wrap');
    if (!orgTreeData || orgTreeData.length === 0) {
        wrap.innerHTML = '<div class="org-tree-empty">暂无组织数据，请点击上方按钮新增</div>';
        return;
    }
    // 合并为一棵统一组织树，按类型筛选
    let displayRoots = orgTreeData;
    if (currentOrgFilter !== '全部') {
        displayRoots = filterOrgTreeByType(orgTreeData, currentOrgFilter);
    }

    let html = '<div class="org-tree-filter-bar">';
    html += `<button class="org-filter-btn ${currentOrgFilter === '全部' ? 'active' : ''}" onclick="setOrgFilter('全部')">全部</button>`;
    html += `<button class="org-filter-btn ${currentOrgFilter === '部门' ? 'active' : ''}" onclick="setOrgFilter('部门')">部门</button>`;
    html += `<button class="org-filter-btn ${currentOrgFilter === '校区' ? 'active' : ''}" onclick="setOrgFilter('校区')">校区</button>`;
    html += '</div>';
    html += '<div class="org-tree-list">';
    if (displayRoots.length === 0) {
        html += '<div class="org-tree-empty">当前筛选条件下无匹配节点</div>';
    } else {
        displayRoots.forEach(root => { html += buildOrgNodeHTML(root, 0); });
    }
    html += '</div>';
    wrap.innerHTML = html;

    // 绑定事件
    wrap.querySelectorAll('.org-node-toggle').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const node = this.closest('.org-tree-node');
            node.classList.toggle('expanded');
            const icon = this.querySelector('.org-toggle-icon');
            icon.textContent = node.classList.contains('expanded') ? '▼' : '▶';
        });
    });
    wrap.querySelectorAll('.org-node-label').forEach(label => {
        label.addEventListener('click', function(e) {
            e.stopPropagation();
            const node = this.closest('.org-tree-node');
            const oid = parseInt(node.dataset.oid);
            document.querySelectorAll('.org-tree-node').forEach(n => n.classList.remove('selected'));
            node.classList.add('selected');
            selectOrgDetail(oid);
        });
    });
}

function buildOrgNodeHTML(node, depth) {
    const hasChildren = node.children && node.children.length > 0;
    const typeTag = node.type === '部门'
        ? '<span class="org-type-tag org-type-dept">部门</span>'
        : '<span class="org-type-tag org-type-campus">校区</span>';
    const indent = depth * 24;

    let html = `<div class="org-tree-node expanded" data-oid="${node.id}" style="padding-left:${indent}px">`;
    html += '<div class="org-node-row">';
    if (hasChildren) {
        html += '<span class="org-node-toggle"><span class="org-toggle-icon">▼</span></span>';
    } else {
        html += '<span class="org-node-toggle org-node-toggle-placeholder"></span>';
    }
    html += `<span class="org-node-label">${esc(node.name)} ${typeTag}</span>`;
    html += '<span class="org-node-actions">';
    html += `<button class="btn-link" title="新增子节点" onclick="event.stopPropagation();showOrgModal(${node.id}, '${node.type}', ${node.id})">+</button>`;
    html += `<button class="btn-link" title="编辑" onclick="event.stopPropagation();showOrgEditModal(${node.id})">✎</button>`;
    html += `<button class="btn-link-danger" title="删除" onclick="event.stopPropagation();deleteOrganization(${node.id}, '${esc(node.name).replace(/'/g, "\\'")}')">✕</button>`;
    html += '</span></div>';

    if (hasChildren) {
        html += '<div class="org-node-children">';
        node.children.forEach(child => { html += buildOrgNodeHTML(child, depth + 1); });
        html += '</div>';
    }

    html += '</div>';
    return html;
}

function selectOrgDetail(oid) {
    selectedOrgId = oid;
    const org = orgFlatData.find(n => n.id === oid);
    if (!org) return;
    const panel = document.getElementById('org-detail-panel');
    const parentOrg = orgFlatData.find(n => n.id === org.parent_id);
    const childrenCount = orgFlatData.filter(n => n.parent_id === org.id).length;
    panel.innerHTML = `
        <div class="org-detail-card">
            <div class="org-detail-header">
                <span class="org-detail-name">${esc(org.name)}</span>
                <span class="org-type-tag ${org.type === '部门' ? 'org-type-dept' : 'org-type-campus'}">${esc(org.type)}</span>
            </div>
            <div class="org-detail-info">
                <div class="org-detail-row"><span class="org-detail-key">ID</span><span class="org-detail-val">${org.id}</span></div>
                <div class="org-detail-row"><span class="org-detail-key">上级组织</span><span class="org-detail-val">${parentOrg ? esc(parentOrg.name) : '无（根节点）'}</span></div>
                <div class="org-detail-row"><span class="org-detail-key">子节点数</span><span class="org-detail-val">${childrenCount}</span></div>
                <div class="org-detail-row"><span class="org-detail-key">排序号</span><span class="org-detail-val">${org.sort_order}</span></div>
                <div class="org-detail-row"><span class="org-detail-key">创建时间</span><span class="org-detail-val">${org.created_at || ''}</span></div>
            </div>
            <div class="org-detail-actions">
                <button class="btn btn-outline btn-sm" onclick="showOrgEditModal(${org.id})">编辑</button>
                <button class="btn btn-outline btn-sm" onclick="showOrgModal(${org.id}, '${org.type}', ${org.id})">新增子节点</button>
                <button class="btn btn-danger btn-sm" onclick="deleteOrganization(${org.id}, '${esc(org.name).replace(/'/g, "\\'")}')">删除</button>
            </div>
        </div>
    `;
}

async function populateOrgParentSelect(excludeId, typeHint) {
    const sel = document.getElementById('org-parent');
    sel.innerHTML = '<option value="0">无（根节点）</option>';
    // 加载最新列表
    try {
        const result = await api('list_organizations', {});
        const flat = result.data.flat || [];
        flat.forEach(org => {
            if (org.id !== excludeId) {
                sel.innerHTML += `<option value="${org.id}">[${esc(org.type)}] ${esc(org.name)}</option>`;
            }
        });
    } catch (e) { /* ignore */ }
}

async function showOrgModal(parentId, typeHint, excludeId) {
    document.getElementById('edit-oid').value = '';
    document.getElementById('modal-org-title').textContent = '新增组织';
    document.getElementById('org-name').value = '';
    document.getElementById('org-type').value = typeHint || '部门';
    document.getElementById('org-sort').value = '0';
    await populateOrgParentSelect(excludeId, typeHint);
    if (parentId > 0) document.getElementById('org-parent').value = parentId;
    openModal('modal-org');
}

async function showOrgEditModal(oid) {
    const org = orgFlatData.find(n => n.id === oid);
    if (!org) { showToast('未找到该组织', 'error'); return; }
    document.getElementById('edit-oid').value = org.id;
    document.getElementById('modal-org-title').textContent = '编辑组织';
    document.getElementById('org-name').value = org.name;
    document.getElementById('org-type').value = org.type;
    document.getElementById('org-sort').value = org.sort_order || 0;
    await populateOrgParentSelect(oid, org.type);
    document.getElementById('org-parent').value = org.parent_id || 0;
    openModal('modal-org');
}

async function saveOrganization() {
    const oid = document.getElementById('edit-oid').value;
    const name = document.getElementById('org-name').value.trim();
    if (!name) return showToast('名称不能为空', 'error');
    const type = document.getElementById('org-type').value;
    const parentId = parseInt(document.getElementById('org-parent').value) || 0;
    const sortOrder = parseInt(document.getElementById('org-sort').value) || 0;
    const data = { name, type, parent_id: parentId, sort_order: sortOrder };
    let result;
    if (oid) {
        data.id = parseInt(oid);
        result = await api('update_organization', data);
    } else {
        result = await api('add_organization', data);
    }
    if (result.error) {
        showToast(result.error, 'error');
        return;
    }
    showToast(result.message);
    closeModal('modal-org');
    loadOrgTree();
}

async function deleteOrganization(oid, name) {
    showCustomConfirm(`确定删除「${name}」？有子节点时不可删除。`, async () => {
        const result = await api('delete_organization', { id: oid });
        if (result.error) { showToast(result.error, 'error'); return; }
        showToast(result.message);
        // 清空右侧详情
        selectedOrgId = null;
        document.getElementById('org-detail-panel').innerHTML = `
            <div class="org-detail-placeholder">
                <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="#ccc" stroke-width="1.5"><path d="M3 3h7v7H3z"/><path d="M14 3h7v7h-7z"/><path d="M14 14h7v7h-7z"/><path d="M3 14h7v7H3z"/></svg>
                <p>请从左侧选择组织节点查看详情</p>
            </div>`;
        loadOrgTree();
    });
}

// ==================== 学科设置 ====================
let subjectTreeData = [];
let subjectFlatData = [];

async function loadSubjects() {
    try {
        const result = await api('list_subjects', {}, 'GET');
        if (result.error) { showToast(result.error, 'error'); return; }
        subjectTreeData = result.tree || [];
        subjectFlatData = result.flat || [];
        renderSubjectTree();
    } catch (e) {
        showToast('加载学科列表失败: ' + e.message, 'error');
    }
}

function renderSubjectTree() {
    const wrap = document.getElementById('subject-tree-wrap');
    if (!wrap) return;
    if (!subjectTreeData || subjectTreeData.length === 0) {
        wrap.innerHTML = '<div class="org-tree-empty">暂无学科数据，请点击上方按钮新增</div>';
        return;
    }
    let html = '<div class="org-tree-list">';
    subjectTreeData.forEach(root => { html += buildSubjectNodeHTML(root, 0); });
    html += '</div>';
    wrap.innerHTML = html;

    // 绑定事件
    wrap.querySelectorAll('.org-node-toggle').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const node = this.closest('.org-tree-node');
            node.classList.toggle('expanded');
            const icon = this.querySelector('.org-toggle-icon');
            icon.textContent = node.classList.contains('expanded') ? '▼' : '▶';
        });
    });
}

function buildSubjectNodeHTML(node, depth) {
    const hasChildren = node.children && node.children.length > 0;
    const indent = depth * 24;
    const levelTag = depth === 0 ? '<span class="org-type-tag org-type-dept">一级</span>' : '<span class="org-type-tag org-type-campus">二级</span>';

    let html = `<div class="org-tree-node expanded" data-sid="${node.id}" style="padding-left:${indent}px">`;
    html += '<div class="org-node-row">';
    if (hasChildren) {
        html += '<span class="org-node-toggle"><span class="org-toggle-icon">▼</span></span>';
    } else {
        html += '<span class="org-node-toggle org-node-toggle-placeholder"></span>';
    }
    html += `<span class="org-node-label">${esc(node.name)} ${levelTag}</span>`;
    html += '<span class="org-node-actions">';
    if (depth === 0) {
        html += `<button class="btn-link" title="新增子学科" onclick="event.stopPropagation();showSubjectModal(${node.id}, 0)">+</button>`;
    }
    html += `<button class="btn-link" title="编辑" onclick="event.stopPropagation();showSubjectEditModal(${node.id})">✎</button>`;
    html += `<button class="btn-link-danger" title="删除" onclick="event.stopPropagation();deleteSubject(${node.id}, '${esc(node.name).replace(/'/g, "\\'")}')">✕</button>`;
    html += '</span></div>';

    if (hasChildren) {
        html += '<div class="org-node-children">';
        node.children.forEach(child => { html += buildSubjectNodeHTML(child, depth + 1); });
        html += '</div>';
    }

    html += '</div>';
    return html;
}

async function showSubjectModal(parentId, hintLevel) {
    document.getElementById('edit-sid').value = '';
    document.getElementById('modal-subject-title').textContent = (hintLevel === -1) ? '新增二级学科' : '新增学科';
    document.getElementById('subject-name').value = '';
    document.getElementById('subject-sort').value = '0';
    await populateSubjectParentSelect(0);
    // hintLevel=-1 表示用户点了「新增二级学科」，提示选择上级
    // parentId=0 且 hintLevel=0 表示新增一级学科
    if (hintLevel === -1 && parentId === 0) {
        // 让用户自己选上级；若只有一级学科，预选提醒
    }
    if (parentId > 0) {
        document.getElementById('subject-parent').value = parentId;
    } else if (hintLevel === 0) {
        document.getElementById('subject-parent').value = '0';
    }
    openModal('modal-subject');
}

async function showSubjectEditModal(sid) {
    const sub = subjectFlatData.find(n => n.id === sid);
    if (!sub) { showToast('未找到该学科', 'error'); return; }
    document.getElementById('edit-sid').value = sub.id;
    document.getElementById('modal-subject-title').textContent = '编辑学科';
    document.getElementById('subject-name').value = sub.name;
    document.getElementById('subject-sort').value = sub.sort_order || 0;
    await populateSubjectParentSelect(sid);
    document.getElementById('subject-parent').value = sub.parent_id || 0;
    openModal('modal-subject');
}

async function populateSubjectParentSelect(excludeId) {
    const sel = document.getElementById('subject-parent');
    sel.innerHTML = '<option value="0">无（一级学科）</option>';
    try {
        // 重新加载以确保列表最新
        const result = await api('list_subjects', {}, 'GET');
        const flat = result.flat || [];
        flat.forEach(sub => {
            if (sub.parent_id === 0 && sub.id !== excludeId) {
                sel.innerHTML += `<option value="${sub.id}">${esc(sub.name)}</option>`;
            }
        });
    } catch (e) { /* ignore */ }
}

async function saveSubject() {
    const sid = document.getElementById('edit-sid').value;
    const name = document.getElementById('subject-name').value.trim();
    if (!name) return showToast('学科名称不能为空', 'error');
    const parentId = parseInt(document.getElementById('subject-parent').value) || 0;
    const sortOrder = parseInt(document.getElementById('subject-sort').value) || 0;
    const data = { name, parent_id: parentId, sort_order: sortOrder };
    let result;
    if (sid) {
        data.id = parseInt(sid);
        result = await api('update_subject', data, 'POST');
    } else {
        result = await api('add_subject', data, 'POST');
    }
    if (result.error) {
        showToast(result.error, 'error');
        // 名称重复时高亮提示
        if (result.error.includes('已存在')) {
            document.getElementById('subject-name').style.borderColor = '#f56c6c';
            setTimeout(() => { document.getElementById('subject-name').style.borderColor = ''; }, 2000);
        }
        return;
    }
    showToast(result.message);
    closeModal('modal-subject');
    loadSubjects();
    loadStats();
}

async function deleteSubject(sid, name) {
    showCustomConfirm(`确定删除「${name}」？有子学科时不可删除。`, async () => {
        const result = await api('delete_subject', { id: sid }, 'POST');
        if (result.error) { showToast(result.error, 'error'); return; }
        showToast(result.message);
        loadSubjects();
        loadStats();
    });
}

// ==================== 班级管理 ====================
let classPage = 1;

async function loadClasses(page) {
    if (page) classPage = page;
    const keyword = document.getElementById('search-class').value;
    const params = new URLSearchParams({ page: classPage, page_size: 15 });
    if (keyword) params.set('keyword', keyword);
    const res = await fetch(API_BASE + 'list_classes&' + params);
    const data = await res.json();
    renderClassTable(data.data);
    renderPagination('pagination-class', data.total, classPage, 15, (p) => { classPage = p; loadClasses(); });
    document.getElementById('stat-classes-inline').textContent = data.total || 0;
}

function renderClassTable(rows) {
    const tbody = document.querySelector('#table-classes tbody');
    if (!rows || rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="13" style="text-align:center;color:#999;padding:30px;">暂无班级数据</td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(r => `
        <tr>
            <td>${r.id}</td>
            <td><a href="javascript:void(0)" class="class-name-link" onclick="viewClassDetail(${r.id}, '${esc(r.name).replace(/'/g, "\\'")}')">${esc(r.name)}</a></td>
            <td>${esc(r.course_name || '')}</td>
            <td>${esc(r.parent_subject || '')}</td>
            <td>${esc(r.child_subject || '')}</td>
            <td>${esc(r.class_type)}</td>
            <td>${r.max_students}</td>
            <td>${r.lesson_hours || '-'}</td>
            <td>${esc(r.can_trial)}</td>
            <td>${esc(r.campus)}</td>
            <td>${esc(r.remark)}</td>
            <td>${r.created_at ? r.created_at.slice(0, 16) : ''}</td>
            <td>
                <div class="action-btns">
                    <button class="btn-link" onclick="showScheduleForm(${r.id})">排课</button>
                    <button class="btn-link" onclick="showClassForm(${r.id})">编辑</button>
                    <button class="btn-link-danger" onclick="deleteClass(${r.id}, '${esc(r.name).replace(/'/g, "\\'")}')">删除</button>
                </div>
            </td>
        </tr>
    `).join('');
}

async function showClassForm(classId) {
    document.getElementById('edit-class-id').value = classId || '';
    document.getElementById('modal-class-title').textContent = classId ? '编辑班级' : '新增班级';
    
    // Reset form
    document.querySelector('input[name="class_type"][value="标准班"]').checked = true;
    document.getElementById('class-name').value = '';
    document.getElementById('class-max-students').value = '';
    document.getElementById('class-lesson-hours').value = '';
    document.querySelector('input[name="can_trial"][value="是"]').checked = true;
    document.getElementById('class-remark').value = '';
    updateClassCount('class-name', 'class-name-count');
    updateClassCount('class-remark', 'class-remark-count');
    
    // Load course dropdown
    const courseSel = document.getElementById('class-course');
    courseSel.innerHTML = '<option value="">请选择关联课程</option>';
    try {
        const res = await fetch(API_BASE + 'list_courses&page=1&page_size=200');
        const data = await res.json();
        if (data.data) {
            data.data.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.name;
                courseSel.appendChild(opt);
            });
        }
    } catch (e) { /* ignore */ }
    
    // Load campus dropdown (organizations WHERE type='校区')
    const campusSel = document.getElementById('class-campus');
    campusSel.innerHTML = '<option value="">请选择当前校区</option>';
    try {
        const res2 = await fetch(API_BASE + 'list_organizations');
        const data2 = await res2.json();
        const flat = (data2.data && data2.data.flat) ? data2.data.flat : [];
        flat.forEach(org => {
            if (org.type === '校区') {
                const opt = document.createElement('option');
                opt.value = org.name;
                opt.textContent = org.name;
                campusSel.appendChild(opt);
            }
        });
    } catch (e) { /* ignore */ }
    
    // If editing, populate fields
    if (classId) {
        try {
            const res3 = await fetch(API_BASE + 'list_classes&page=1&page_size=100');
            const data3 = await res3.json();
            const cls = (data3.data || []).find(c => c.id == classId);
            if (cls) {
                document.querySelector('input[name="class_type"][value="' + esc(cls.class_type) + '"]').checked = true;
                document.getElementById('class-course').value = cls.course_id;
                document.getElementById('class-name').value = cls.name;
                document.getElementById('class-max-students').value = cls.max_students;
                document.getElementById('class-lesson-hours').value = cls.lesson_hours;
                document.querySelector('input[name="can_trial"][value="' + esc(cls.can_trial) + '"]').checked = true;
                document.getElementById('class-campus').value = cls.campus;
                document.getElementById('class-remark').value = cls.remark;
                updateClassCount('class-name', 'class-name-count');
                updateClassCount('class-remark', 'class-remark-count');
            }
        } catch (e) { /* ignore */ }
    }
    
    openModal('modal-class-form');
}

async function saveClass() {
    const id = document.getElementById('edit-class-id').value;
    const courseId = parseInt(document.getElementById('class-course').value) || 0;
    const name = document.getElementById('class-name').value.trim();
    const classType = document.querySelector('input[name="class_type"]:checked').value;
    const maxStudents = parseInt(document.getElementById('class-max-students').value) || 0;
    const lessonHours = parseInt(document.getElementById('class-lesson-hours').value) || 0;
    const canTrial = document.querySelector('input[name="can_trial"]:checked').value;
    const campus = document.getElementById('class-campus').value;
    const remark = document.getElementById('class-remark').value.trim();
    
    if (!courseId) return showToast('请选择关联课程', 'error');
    if (!name) return showToast('班级名称不能为空', 'error');
    if (name.length > 20) return showToast('班级名称最长20字', 'error');
    if (maxStudents <= 0) return showToast('招生人数必须大于0', 'error');
    if (lessonHours % 2 !== 0) return showToast('授课课时必须为偶数', 'error');
    if (!campus) return showToast('请选择当前校区', 'error');
    if (remark.length > 200) return showToast('备注最长200字', 'error');
    
    const data = { course_id: courseId, name, class_type: classType, max_students: maxStudents, lesson_hours: lessonHours, can_trial: canTrial, campus, remark };
    let result;
    try {
        if (id) {
            data.id = parseInt(id);
            result = await api('update_class', data);
        } else {
            result = await api('add_class', data);
        }
    } catch (e) {
        showToast('网络请求失败：' + e.message, 'error');
        return;
    }
    if (result.error) { showToast(result.error, 'error'); return; }
    showToast(result.message);
    closeModal('modal-class-form');
    loadClasses();
    loadStats();
}

async function deleteClass(id, name) {
    showCustomConfirm('确定删除班级「' + name + '」？', async () => {
        const result = await api('delete_class', { id: id });
        if (result.error) { showToast(result.error, 'error'); return; }
        showToast(result.message);
        loadClasses();
        loadStats();
    });
}

function updateClassCount(inputId, countId) {
    const el = document.getElementById(inputId);
    const cnt = document.getElementById(countId);
    if (el && cnt) {
        cnt.textContent = el.value.length + '/' + (el.maxLength || 200);
    }
}

function esc(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}


// ==================== 排课管理 ====================
function onScheduleRuleChange() {
    const ruleType = document.querySelector('input[name="schedule_rule_type"]:checked').value;
    document.getElementById('schedule-rule-section').style.display = ruleType === '按规则排课' ? '' : 'none';
    document.getElementById('schedule-date-section').style.display = ruleType === '按日期排课' ? '' : 'none';
}

// ==================== Flatpickr 日期选择器 ====================

let fpRange = null;
let fpMulti = null;

function formatDate(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + day;
}

function initScheduleDatePickers() {
    // 销毁旧实例，避免重复绑定
    if (fpRange) { fpRange.destroy(); fpRange = null; }
    if (fpMulti) { fpMulti.destroy(); fpMulti = null; }

    // 日期范围选择器
    fpRange = flatpickr('#schedule-date-range', {
        mode: 'range',
        locale: 'zh',
        dateFormat: 'Y-m-d',
        allowInput: false,
        disableMobile: true,
        defaultDate: [],
        onChange: function(dates) {
            if (dates.length === 2) {
                document.getElementById('schedule-date-range').value =
                    formatDate(dates[0]) + ' 至 ' + formatDate(dates[1]);
            } else if (dates.length === 0) {
                document.getElementById('schedule-date-range').value = '';
            }
        }
    });

    // 多日期选择器
    fpMulti = flatpickr('#schedule-custom-dates', {
        mode: 'multiple',
        locale: 'zh',
        dateFormat: 'Y-m-d',
        allowInput: false,
        disableMobile: true,
        defaultDate: [],
        onChange: function(dates) {
            renderCustomDateTags(dates);
            if (dates.length > 0) {
                document.getElementById('schedule-custom-dates').placeholder =
                    '已选择 ' + dates.length + ' 个日期';
            } else {
                document.getElementById('schedule-custom-dates').placeholder = '点击选择多个日期';
            }
        }
    });
}

function renderCustomDateTags(dates) {
    const container = document.getElementById('schedule-custom-dates-tags');
    if (!container) return;
    if (dates.length === 0) { container.innerHTML = ''; return; }
    container.innerHTML = dates.map((d, i) =>
        '<span class="date-tag">' + formatDate(d) +
        '<span class="date-tag-remove" data-idx="' + i + '">&times;</span></span>'
    ).join('');

    // 点击 × 移除该日期
    container.querySelectorAll('.date-tag-remove').forEach(el => {
        el.addEventListener('click', function(e) {
            e.stopPropagation();
            const idx = parseInt(this.dataset.idx);
            const updated = fpMulti.selectedDates.filter((_, i) => i !== idx);
            fpMulti.setDate(updated);
            renderCustomDateTags(updated);
            if (updated.length > 0) {
                document.getElementById('schedule-custom-dates').placeholder =
                    '已选择 ' + updated.length + ' 个日期';
            } else {
                document.getElementById('schedule-custom-dates').placeholder = '点击选择多个日期';
            }
        });
    });
}

function toggleWeekday(btn) {
    btn.classList.toggle('active');
    updateScheduleTimeSlots();
}

function getSelectedWeekdays() {
    const buttons = document.querySelectorAll('#weekday-buttons .weekday-btn.active');
    return Array.from(buttons).map(b => parseInt(b.dataset.day));
}

function getSelectedWeekdayNames() {
    const dayNames = {1:'周一',2:'周二',3:'周三',4:'周四',5:'周五',6:'周六',7:'周日'};
    return getSelectedWeekdays().map(d => dayNames[d]);
}

function updateScheduleTimeSlots() {
    const container = document.getElementById('schedule-time-slots');
    const selectedDays = getSelectedWeekdayNames();
    if (selectedDays.length === 0) {
        container.innerHTML = '<div class="schedule-time-hint">请先选择上课周期</div>';
        return;
    }
    const existingInputs = {};
    container.querySelectorAll('.schedule-time-row').forEach(row => {
        const dayKey = row.dataset.day;
        const startEl = row.querySelector('.schedule-time-start');
        const endEl = row.querySelector('.schedule-time-end');
        if (startEl && endEl) {
            existingInputs[dayKey] = { start: startEl.value, end: endEl.value };
        }
    });
    container.innerHTML = selectedDays.map((name, i) => {
        const dayNum = getSelectedWeekdays()[i];
        const prev = existingInputs[String(dayNum)] || { start: '', end: '' };
        return '<div class="schedule-time-row" data-day="' + dayNum + '" style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">' +
            '<span style="min-width:40px;font-size:13px;color:#333;">' + name + '</span>' +
            '<input type="time" class="schedule-time-start" value="' + prev.start + '" style="flex:1;">' +
            '<span style="color:#666;">至</span>' +
            '<input type="time" class="schedule-time-end" value="' + prev.end + '" style="flex:1;">' +
        '</div>';
    }).join('');
}

function getScheduleTimeSlots() {
    const slots = {};
    document.querySelectorAll('.schedule-time-row').forEach(row => {
        const day = row.dataset.day;
        const start = row.querySelector('.schedule-time-start').value;
        const end = row.querySelector('.schedule-time-end').value;
        if (start && end) {
            slots[day] = { start, end };
        }
    });
    return slots;
}

function setScheduleTimeSlots(slots) {
    const container = document.getElementById('schedule-time-slots');
    container.querySelectorAll('.schedule-time-row').forEach(row => {
        const day = row.dataset.day;
        if (slots[day]) {
            const startEl = row.querySelector('.schedule-time-start');
            const endEl = row.querySelector('.schedule-time-end');
            if (startEl) startEl.value = slots[day].start || '';
            if (endEl) endEl.value = slots[day].end || '';
        }
    });
}

function onHolidayToggle() {
    // placeholder for future holiday logic
}

async function showScheduleForm(classId, scheduleId) {
    document.getElementById('schedule-class-id').value = classId || '';
    document.getElementById('edit-schedule-id').value = scheduleId || '';
    document.getElementById('modal-schedule-title').textContent = scheduleId ? '编辑排课' : '排课设置';

    // Reset form
    document.querySelector('input[name="schedule_rule_type"][value="按规则排课"]').checked = true;
    document.getElementById('schedule-date-range').value = '';
    document.getElementById('schedule-holiday').checked = false;
    document.getElementById('schedule-custom-dates').value = '';
    document.getElementById('schedule-rule-section').style.display = '';
    document.getElementById('schedule-date-section').style.display = 'none';
    document.querySelectorAll('#weekday-buttons .weekday-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('schedule-time-slots').innerHTML = '<div class="schedule-time-hint">请先选择上课周期</div>';

    // Load teacher dropdown
    const teacherSel = document.getElementById('schedule-teacher');
    teacherSel.innerHTML = '<option value="">搜索授课老师</option>';
    try {
        const res = await fetch(API_BASE + 'get_employees&page=1&page_size=200');
        const data = await res.json();
        if (data.data) {
            data.data.filter(emp => emp.is_teacher === '是').forEach(emp => {
                const opt = document.createElement('option');
                opt.value = emp.name;
                opt.textContent = emp.name + (emp.department ? ' - ' + emp.department : '');
                teacherSel.appendChild(opt);
            });
        }
    } catch (e) { /* ignore */ }

    // Load classroom dropdown
    const classroomSel = document.getElementById('schedule-classroom');
    classroomSel.innerHTML = '<option value="">请选择上课教室</option>';
    try {
        const res2 = await fetch(API_BASE + 'list_classrooms');
        const data2 = await res2.json();
        if (data2.data) {
            data2.data.forEach(cr => {
                const opt = document.createElement('option');
                opt.value = cr.name;
                opt.textContent = cr.name + (cr.capacity ? ' (' + cr.capacity + '人)' : '');
                classroomSel.appendChild(opt);
            });
        }
    } catch (e) { /* ignore */ }

    // Initialize flatpickr BEFORE loading editing data
    initScheduleDatePickers();

    // If editing, load existing
    if (scheduleId) {
        try {
            const res3 = await fetch(API_BASE + 'get_schedule&id=' + scheduleId);
            const data3 = await res3.json();
            const sch = data3.data;
            if (sch) {
                document.querySelector('input[name="schedule_rule_type"][value="' + esc(sch.rule_type) + '"]').checked = true;
                onScheduleRuleChange();
                if (sch.rule_type === '按规则排课' && sch.start_date && sch.end_date) {
                    fpRange.setDate([sch.start_date, sch.end_date], true);
                }
                document.getElementById('schedule-holiday').checked = sch.holiday_enabled == 1;
                if (sch.weekdays) {
                    const days = sch.weekdays.split(',').map(d => parseInt(d.trim()));
                    document.querySelectorAll('#weekday-buttons .weekday-btn').forEach(b => {
                        if (days.includes(parseInt(b.dataset.day))) b.classList.add('active');
                        else b.classList.remove('active');
                    });
                }
                updateScheduleTimeSlots();
                let timeSlots = {};
                try { timeSlots = JSON.parse(sch.time_slots || '{}'); } catch(e) {}
                setTimeout(() => setScheduleTimeSlots(timeSlots), 100);
                document.getElementById('schedule-teacher').value = sch.teacher || '';
                document.getElementById('schedule-classroom').value = sch.classroom || '';
                if (sch.rule_type === '按日期排课') {
                    const dateList = sch.start_date ? sch.start_date.split(/[,，\s\n]+/).filter(d => d) : [];
                    if (dateList.length > 0) {
                        fpMulti.setDate(dateList, true);
                    }
                }
            }
        } catch (e) { /* ignore */ }
    }

    openModal('modal-schedule-form');
}

async function saveSchedule() {
    const classId = parseInt(document.getElementById('schedule-class-id').value) || 0;
    const scheduleId = parseInt(document.getElementById('edit-schedule-id').value) || 0;
    const ruleType = document.querySelector('input[name="schedule_rule_type"]:checked').value;
    if (!classId) return showToast('班级信息缺失', 'error');

    let data = {
        class_id: classId,
        rule_type: ruleType,
        teacher: document.getElementById('schedule-teacher').value,
        classroom: document.getElementById('schedule-classroom').value
    };

    if (ruleType === '按规则排课') {
        const startDate = fpRange.selectedDates.length > 0 ? formatDate(fpRange.selectedDates[0]) : '';
        const endDate = fpRange.selectedDates.length > 1 ? formatDate(fpRange.selectedDates[1]) : '';
        const selectedWeekdays = getSelectedWeekdays();
        const timeSlots = getScheduleTimeSlots();
        const holidayEnabled = document.getElementById('schedule-holiday').checked ? 1 : 0;

        if (!startDate) return showToast('请选择开课日期', 'error');
        if (!endDate) return showToast('请选择结课日期', 'error');
        if (startDate > endDate) return showToast('开课日期不能晚于结课日期', 'error');
        if (selectedWeekdays.length === 0) return showToast('请选择上课周期', 'error');
        if (Object.keys(timeSlots).length === 0) return showToast('请设置上课时间', 'error');

        data.start_date = startDate;
        data.end_date = endDate;
        data.weekdays = selectedWeekdays.join(',');
        data.time_slots = JSON.stringify(timeSlots);
        data.holiday_enabled = holidayEnabled;
    } else {
        const customDates = fpMulti.selectedDates.length > 0
            ? fpMulti.selectedDates.map(d => formatDate(d)).join(',')
            : '';
        if (!customDates) return showToast('请选择上课日期', 'error');
        data.start_date = customDates;
        data.end_date = customDates;
        data.weekdays = '';
        data.time_slots = '{}';
        data.holiday_enabled = 0;
    }

    let result;
    if (scheduleId) {
        data.id = scheduleId;
        result = await api('update_schedule', data);
    } else {
        result = await api('add_schedule', data);
    }
    if (result.error) { showToast(result.error, 'error'); return; }
    showToast(result.message);
    closeModal('modal-schedule-form');
    if (currentClassDetailId) {
        loadClassSchedules();
    } else {
        loadClasses();
    }
}

async function deleteSchedule(id) {
    showCustomConfirm('确定删除该排课记录？', async () => {
        const result = await api('delete_schedule', { id: id });
        if (result.error) { showToast(result.error, 'error'); return; }
        showToast(result.message);
        if (currentClassDetailId) {
            loadClassSchedules();
        } else {
            loadClasses();
        }
    });
}


// ==================== 教室管理 ====================
async function loadClassrooms() {
    const keyword = document.getElementById('search-classroom').value;
    const params = new URLSearchParams();
    if (keyword) params.set('keyword', keyword);
    const qs = params.toString();
    const res = await fetch(API_BASE + 'list_classrooms' + (qs ? '&' + qs : ''));
    const data = await res.json();
    renderClassroomTable(data.data);
}

function renderClassroomTable(rows) {
    const tbody = document.querySelector('#table-classrooms tbody');
    if (!rows || rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#999;padding:30px;">暂无教室数据</td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(r => `<tr>
        <td>${esc(r.name)}</td>
        <td>${r.capacity || '-'}</td>
        <td>${esc(r.campus || '-')}</td>
        <td>${esc(r.remark || '-')}</td>
        <td>${esc(r.created_at || '-')}</td>
        <td>
            <button class="btn btn-sm btn-outline" onclick="showClassroomForm(${r.id})">编辑</button>
            <button class="btn btn-sm btn-danger" onclick="deleteClassroom(${r.id})" style="margin-left:4px;">删除</button>
        </td>
    </tr>`).join('');
}

async function showClassroomForm(id) {
    document.getElementById('edit-classroom-id').value = id || '';
    document.getElementById('modal-classroom-title').textContent = id ? '编辑教室' : '新增教室';
    document.getElementById('classroom-name').value = '';
    document.getElementById('classroom-capacity').value = '';
    document.getElementById('classroom-remark').value = '';

    // Load campus dropdown
    const campusSel = document.getElementById('classroom-campus');
    campusSel.innerHTML = '<option value="">请选择校区</option>';
    try {
        const res = await fetch(API_BASE + 'list_organizations');
        const data = await res.json();
        const flat = (data.data && data.data.flat) ? data.data.flat : [];
        flat.forEach(org => {
            if (org.type === '校区') {
                const opt = document.createElement('option');
                opt.value = org.name;
                opt.textContent = org.name;
                campusSel.appendChild(opt);
            }
        });
    } catch (e) { /* ignore */ }

    if (id) {
        try {
            const res = await fetch(API_BASE + 'list_classrooms');
            const data = await res.json();
            if (data.data) {
                const cr = data.data.find(r => r.id == id);
                if (cr) {
                    document.getElementById('classroom-name').value = cr.name || '';
                    document.getElementById('classroom-capacity').value = cr.capacity || '';
                    document.getElementById('classroom-campus').value = cr.campus || '';
                    document.getElementById('classroom-remark').value = cr.remark || '';
                }
            }
        } catch (e) { /* ignore */ }
    }

    openModal('modal-classroom-form');
}

async function saveClassroom() {
    const id = parseInt(document.getElementById('edit-classroom-id').value) || 0;
    const name = document.getElementById('classroom-name').value.trim();
    const capacity = parseInt(document.getElementById('classroom-capacity').value) || 0;
    const campus = document.getElementById('classroom-campus').value;
    const remark = document.getElementById('classroom-remark').value.trim();

    if (!name) return showToast('教室名称不能为空', 'error');

    const data = { name, capacity, campus, remark };
    if (id) data.id = id;

    const action = id ? 'update_classroom' : 'add_classroom';
    const result = await api(action, data);
    if (result.error) { showToast(result.error, 'error'); return; }
    showToast(result.message);
    closeModal('modal-classroom-form');
    loadClassrooms();
}

async function deleteClassroom(id) {
    showCustomConfirm('确定删除该教室？', async () => {
        const result = await api('delete_classroom', { id: id });
        if (result.error) { showToast(result.error, 'error'); return; }
        showToast(result.message);
        loadClassrooms();
    });
}


// ==================== 学员管理 ====================
async function loadStudents() {
    const keyword = document.getElementById('search-student').value;
    const params = new URLSearchParams({ page: studentPage, page_size: 15 });
    if (keyword) params.set('keyword', keyword);
    const res = await fetch(API_BASE + 'list_students&' + params);
    const data = await res.json();
    renderStudentTable(data.data);
    renderPagination('pagination-student', data.total, studentPage, 15, (p) => { studentPage = p; loadStudents(); });
    document.getElementById('stat-students-inline').textContent = data.total || 0;
}

function renderStudentTable(rows) {
    const tbody = document.querySelector('#table-students tbody');
    if (!rows || rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#999;padding:30px;">暂无学员数据</td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(r => `
        <tr>
            <td>${r.id}</td>
            <td style="font-family:monospace;font-size:12px;">${esc(r.student_no || '')}</td>
            <td><a class="student-name-link" href="javascript:void(0)" onclick="viewStudent(${r.id})">${esc(r.name)}</a></td>
            <td>${esc(r.phone)}</td>
            <td>${esc(r.campus || '')}</td>
            <td>${r.order_count || 0}</td>
            <td>${esc(r.class_names || '')}</td>
            <td>
                <div class="action-btns">
                    <button class="btn btn-primary btn-sm" onclick="goEnroll(${r.id})">报名</button>
                    <button class="btn btn-outline-gray btn-sm" onclick="viewStudent(${r.id})">详情</button>
                    <button class="btn btn-outline-gray btn-sm" onclick="editStudent(${r.id})">编辑</button>
                </div>
            </td>
        </tr>
    `).join('');
}


async function populateStudentSourceSelect(selectedValue) {
    const sel = document.getElementById('student-source');
    if (!sel) return;
    sel.innerHTML = '<option value="">请选择</option>';
    try {
        const result = await api('list_channels', null, 'GET');
        const channels = Array.isArray(result.data) ? result.data : [];
        channels.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.name;
            opt.textContent = c.name;
            sel.appendChild(opt);
        });
        if (selectedValue) sel.value = selectedValue;
    } catch (e) { /* ignore */ }
}

async function editStudent(sid) {
    const res = await fetch(API_BASE + 'list_students&page=1&page_size=1&keyword=');
    const data = await res.json();
    let student = null;
    if (data.data) student = data.data.find(s => s.id === sid);
    if (!student) {
        const res2 = await fetch(API_BASE + 'list_students&page=1&page_size=' + (sid + 10));
        const data2 = await res2.json();
        if (data2.data) student = data2.data.find(s => s.id === sid);
    }
    if (!student) { showToast('未找到该学员', 'error'); return; }
    document.getElementById('edit-sid').value = student.id;
    document.getElementById('modal-student-title').textContent = '编辑学员';
    document.getElementById('student-name').value = student.name || '';
    document.getElementById('student-phone').value = student.phone || '';
    document.getElementById('student-follow-status').value = student.follow_status || '';
    await populateStudentSourceSelect(student.source || '');
    openModal('modal-student');
}

async function saveStudent() {
    const sid = document.getElementById('edit-sid').value;
    const name = document.getElementById('student-name').value.trim();
    const phone = document.getElementById('student-phone').value.trim();
    if (!name) return showToast('姓名不能为空', 'error');
    if (!phone) return showToast('手机号不能为空', 'error');
    const data = {
        name,
        phone,
        source: document.getElementById('student-source').value.trim(),
        follow_status: document.getElementById('student-follow-status').value
    };
    let result;
    if (sid) {
        data.id = parseInt(sid);
        result = await api('update_student', data);
    } else {
        result = await api('add_student', data);
    }
    if (result.error) {
        showToast(result.error, 'error');
        if (result.error.indexOf('手机号') !== -1) {
            const input = document.getElementById('student-phone');
            if (input) { input.style.borderColor = '#e74c3c'; input.style.backgroundColor = '#fff5f5'; input.focus(); setTimeout(() => { input.style.borderColor = ''; input.style.backgroundColor = ''; }, 3000); }
        }
        return;
    }
    showToast(result.message);
    closeModal('modal-student');
    loadStudents();
}

async function deleteStudent(sid, name) {
    showCustomConfirm(`确定删除学员「${name}」？此操作将同时删除该学员的所有订单，且不可恢复。`, async () => {
        const result = await api('delete_student', { id: sid });
        showToast(result.message);
        loadStudents();
        loadStats();
    });
}

// ==================== 学员详情 ====================
let currentViewStudentId = null;

async function viewStudent(sid) {
    currentViewStudentId = sid;
    const res = await fetch(API_BASE + 'get_student&id=' + sid);
    const data = await res.json();
    if (data.error) { showToast(data.error, 'error'); return; }
    const student = data.student;

    // 基础信息
    let infoHtml = `<div style="background:#fff;border:1px solid #e8e8e8;border-radius:8px;padding:20px;">
        <h4 style="margin:0 0 12px;font-size:16px;">学员信息</h4>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:14px;">
            <div><span style="color:#888;">姓名：</span>${esc(student.name)}</div>
            <div><span style="color:#888;">手机号：</span>${esc(student.phone)}</div>
            <div><span style="color:#888;">来源：</span>${esc(student.source)}</div>
            <div><span style="color:#888;">跟进状态：</span>${esc(student.follow_status)}</div>
            <div><span style="color:#888;">学号：</span><span style="font-family:monospace;">${esc(student.student_no || '')}</span></div>
            <div><span style="color:#888;">创建时间：</span>${student.created_at ? student.created_at.slice(0, 16) : ''}</div>
        </div>
    </div>`;
    document.getElementById('student-detail-info').innerHTML = infoHtml;

    activatePanel('panel-student-detail');
    highlightLeafByPanel('panel-student-detail');

    // 默认激活报读课程标签
    switchStudentDetailTab('tab-courses');
}

function switchStudentDetailTab(tabId) {
    // 标签按钮状态
    document.querySelectorAll('.sdt-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === tabId));
    // 面板显示
    document.querySelectorAll('.sdt-panel').forEach(p => p.classList.toggle('active', p.id === tabId));
    // 加载对应数据
    const sid = currentViewStudentId;
    if (!sid) return;
    if (tabId === 'tab-courses') loadStudentCourses(sid);
    else if (tabId === 'tab-orders') loadStudentOrders(sid);
    else if (tabId === 'tab-attendance') loadAttendance(sid);
}

// 标签页点击事件委托
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('sdt-tab')) {
        switchStudentDetailTab(e.target.dataset.tab);
    }
});

// ==================== 学员详情 - 报读课程 ====================
async function loadStudentCourses(sid) {
    const container = document.getElementById('student-courses-content');
    container.innerHTML = '<div style="text-align:center;color:#999;padding:20px;">加载中...</div>';
    try {
        const res = await fetch(API_BASE + 'get_student_courses&student_id=' + sid);
        const data = await res.json();
        const rows = data.data || [];
        if (rows.length === 0) {
            container.innerHTML = '<div style="text-align:center;color:#999;padding:30px;">暂未报读课程</div>';
            return;
        }
        container.innerHTML = `<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <span style="font-size:14px;color:#888;">共 ${rows.length} 门课程</span>
            <button class="btn btn-primary btn-sm" onclick="showClassEnrollModal(${sid})">分班</button>
        </div>
        <div class="table-wrap"><table><thead><tr>
            <th>课程名称</th><th>校区</th><th>学科</th><th>价格方案</th><th>报价单</th><th>课时数量</th><th>实际价格</th><th>已消耗课时</th><th>已消耗金额</th><th>剩余课时</th><th>剩余金额</th><th>报名时间</th>
        </tr></thead><tbody>
        ${rows.map(r => `<tr>
            <td>${esc(r.name)}</td>
            <td>${esc(r.campus || '-')}</td>
            <td>${esc(r.subject)}</td>
            <td>${esc(r.plan_name)}</td>
            <td>${esc(r.item_name)}</td>
            <td>${r.lesson_count || ''}</td>
            <td>${r.actual_price != null ? '¥' + Number(r.actual_price).toFixed(2) : ''}</td>
            <td>${r.consumed_lessons != null ? r.consumed_lessons : 0}</td>
            <td>${r.consumed_amount != null ? '¥' + Number(r.consumed_amount).toFixed(2) : '¥0.00'}</td>
            <td>${r.remaining_lessons != null ? r.remaining_lessons : (r.lesson_count || 0)}</td>
            <td>${r.remaining_amount != null ? '¥' + Number(r.remaining_amount).toFixed(2) : '¥0.00'}</td>
            <td>${r.created_at ? r.created_at.slice(0, 16) : ''}</td>
        </tr>`).join('')}
        </tbody></table></div>`;
    } catch (e) {
        container.innerHTML = '<div style="text-align:center;color:#e74c3c;padding:20px;">加载失败</div>';
    }
}

// ==================== 学员详情 - 交易订单 ====================
async function loadStudentOrders(sid) {
    const container = document.getElementById('student-orders-content');
    container.innerHTML = '<div style="text-align:center;color:#999;padding:20px;">加载中...</div>';
    try {
        const res = await fetch(API_BASE + 'list_orders&page=1&page_size=100&keyword=');
        const data = await res.json();
        let rows = (data.data || []).filter(o => o.student_id == sid);
        // 如果列表API没返回student_id字段，用更精确的方式
        if (rows.length === 0 && data.data && data.data.length > 0) {
            // 尝试通过get_student获取orders
            const res2 = await fetch(API_BASE + 'get_student&id=' + sid);
            const data2 = await res2.json();
            rows = (data2.orders || []).map(o => ({
                ...o,
                student_name: data2.student.name,
                course_name: o.course_name || ''
            }));
        }
        if (rows.length === 0) {
            container.innerHTML = '<div style="text-align:center;color:#999;padding:30px;">暂无交易订单</div>';
            return;
        }
        container.innerHTML = `<span style="font-size:14px;color:#888;">共 ${rows.length} 笔订单</span>
        <div class="table-wrap" style="margin-top:8px;"><table><thead><tr>
            <th>订单号</th><th>父订单号</th><th>学号</th><th>编号</th><th>学员姓名</th><th>校区</th><th>课程名称</th><th>价格方案</th><th>报价单名称</th><th>课时数量</th><th>订单金额</th><th>现金</th><th>美团</th><th>订单创建时间</th><th>订单支付时间</th><th>订单类型</th>
        </tr></thead><tbody>
        ${rows.map(r => {
            const cash = Number(r.cash_amount) || 0;
            const mt = Number(r.meituan_amount) || 0;
            let orderTypeHtml = '';
            const ot = (r.order_type || '').trim();
            if (ot === '新报') orderTypeHtml = '<span class="tag tag-new-enroll">新报</span>';
            else if (ot === '续费') orderTypeHtml = '<span class="tag tag-renewal">续费</span>';
            else if (ot === '小课包') orderTypeHtml = '<span class="tag tag-small-pack">小课包</span>';
            return `<tr>
            <td style="font-family:monospace;font-size:12px;">${esc(r.order_no || '')}</td>
            <td style="font-family:monospace;font-size:12px;">${esc(r.parent_order_no || '')}</td>
            <td style="font-family:monospace;font-size:12px;">${esc(r.student_no || '')}</td>
            <td>${r.id}</td>
            <td>${esc(r.student_name)}</td>
            <td>${esc(r.campus || '-')}</td>
            <td>${esc(r.course_name)}</td>
            <td>${esc(r.plan_name)}</td>
            <td>${esc(r.item_name)}</td>
            <td>${r.lesson_count || ''}</td>
            <td>${r.actual_price != null ? '¥' + Number(r.actual_price).toFixed(2) : ''}</td>
            <td>${cash > 0 ? '¥' + cash.toFixed(2) : '-'}</td>
            <td>${mt > 0 ? '¥' + mt.toFixed(2) : '-'}</td>
            <td>${r.created_at ? r.created_at.slice(0, 16) : ''}</td>
            <td>${r.paid_at ? r.paid_at.slice(0, 16) : ''}</td>
            <td>${orderTypeHtml}</td>
        </tr>`;
        }).join('')}
        </tbody></table></div>`;
    } catch (e) {
        container.innerHTML = '<div style="text-align:center;color:#e74c3c;padding:20px;">加载失败</div>';
    }
}

// ==================== 学员详情 - 上课记录 ====================
let currentEditAttId = null;

async function loadAttendance(sid) {
    const tbody = document.getElementById('attendance-tbody');
    tbody.innerHTML = '<tr><td colspan="12" style="text-align:center;color:#999;padding:20px;">加载中...</td></tr>';
    try {
        const res = await fetch(API_BASE + 'list_attendance&student_id=' + sid);
        const data = await res.json();
        const rows = data.data || [];
        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="12" style="text-align:center;color:#999;padding:30px;">暂无上课记录</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(r => {
            let statusClass = 'status-出勤';
            if (r.status === '缺勤') statusClass = 'status-缺勤';
            else if (r.status === '请假') statusClass = 'status-请假';
            return `<tr>
                <td>${esc(r.campus)}</td>
                <td>${esc(r.course_name)}</td>
                <td>${esc(r.subject_level1)}</td>
                <td>${esc(r.subject_level2)}</td>
                <td>${esc(r.class_name)}</td>
                <td>${esc(r.teacher)}</td>
                <td>${r.lesson_date}</td>
                <td>${esc(r.class_time)}</td>
                <td>${r.attended_at ? r.attended_at.slice(0, 19) : ''}</td>
                <td><span class="status-tag ${statusClass}">${esc(r.status)}</span></td>
                <td>${r.deducted_lessons || 0}</td>
                <td>¥${(parseFloat(r.consumed_amount) || 0).toFixed(2)}</td>
            </tr>`;
        }).join('');
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="12" style="text-align:center;color:#e74c3c;padding:20px;">加载失败</td></tr>';
    }
}

async function showAttendanceModal() {
    document.getElementById('modal-attendance-title').textContent = '新增上课记录';
    document.getElementById('edit-att-id').value = '';
    document.getElementById('att-lesson-date').value = '';
    document.getElementById('att-status').value = '出勤';
    document.getElementById('att-teacher').value = '';
    document.getElementById('att-amount').value = '';
    document.getElementById('att-class-time').value = '';
    document.getElementById('att-campus').value = '';
    currentEditAttId = null;
    await loadAttendanceCourseSelect();
    await loadAttendanceClassSelect();
    openModal('modal-attendance');
}

async function loadAttendanceCourseSelect(selectedCourseId) {
    const sel = document.getElementById('att-course');
    sel.innerHTML = '<option value="">请选择课程</option>';
    window._courseSubjects = {};
    try {
        const res = await fetch(API_BASE + 'get_student_courses&student_id=' + currentViewStudentId);
        const data = await res.json();
        const courses = data.data || [];
        const seen = {};
        courses.forEach(c => {
            if (!seen[c.id]) {
                seen[c.id] = true;
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.name + (c.subject ? ' (' + c.subject + ')' : '');
                sel.appendChild(opt);
                window._courseSubjects[c.id] = c.subject || '';
            }
        });
        if (selectedCourseId) sel.value = selectedCourseId;
        onCourseChangeInAttendance();
    } catch (e) { /* ignore */ }
}

async function loadAttendanceClassSelect(selectedClassName) {
    const sel = document.getElementById('att-class');
    sel.innerHTML = '<option value="">请选择班级（选填）</option>';
    window._classCampuses = {};
    try {
        const res = await fetch(API_BASE + 'list_classes');
        const data = await res.json();
        const classes = data.data || [];
        classes.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.name;
            opt.textContent = c.name + (c.campus ? ' - ' + c.campus : '');
            sel.appendChild(opt);
            window._classCampuses[c.name] = c.campus || '';
        });
        if (selectedClassName) sel.value = selectedClassName;
    } catch (e) { /* ignore */ }
}

async function editAttendance(id) {
    // 先获取当前列表中的数据
    try {
        const res = await fetch(API_BASE + 'list_attendance&student_id=' + currentViewStudentId);
        const data = await res.json();
        const record = (data.data || []).find(r => r.id === id);
        if (!record) { showToast('未找到该记录', 'error'); return; }
        document.getElementById('modal-attendance-title').textContent = '编辑上课记录';
        document.getElementById('edit-att-id').value = record.id;
        document.getElementById('att-lesson-date').value = record.lesson_date || '';
        document.getElementById('att-status').value = record.status || '出勤';
        document.getElementById('att-teacher').value = record.teacher || '';
        document.getElementById('att-amount').value = record.consumed_amount || '';
        document.getElementById('att-subject1').value = record.subject_level1 || '';
        document.getElementById('att-subject2').value = record.subject_level2 || '';
        document.getElementById('att-class-time').value = record.class_time || '';
        document.getElementById('att-campus').value = record.campus || '';
        currentEditAttId = id;
        await loadAttendanceCourseSelect(record.course_id);
        await loadAttendanceClassSelect(record.class_name);
        openModal('modal-attendance');
    } catch (e) {
        showToast('加载失败', 'error');
    }
}

function onCourseChangeInAttendance() {
    const courseId = parseInt(document.getElementById('att-course').value) || 0;
    const subj = (window._courseSubjects && window._courseSubjects[courseId]) || '';
    const parts = subj.split(' > ');
    document.getElementById('att-subject1').value = parts[0] || '';
    document.getElementById('att-subject2').value = parts[1] || '';
}

function onClassChangeInAttendance() {
    const className = document.getElementById('att-class').value;
    const campusMap = window._classCampuses || {};
    document.getElementById('att-campus').value = campusMap[className] || '';
}

async function saveAttendance() {
    const sid = currentViewStudentId;
    const courseId = parseInt(document.getElementById('att-course').value) || 0;
    const lessonDate = document.getElementById('att-lesson-date').value;
    const status = document.getElementById('att-status').value;
    const className = document.getElementById('att-class').value;
    const teacher = document.getElementById('att-teacher').value.trim();
    const subjectLevel1 = document.getElementById('att-subject1').value.trim();
    const subjectLevel2 = document.getElementById('att-subject2').value.trim();
    const classTime = document.getElementById('att-class-time').value.trim();
    const campus = document.getElementById('att-campus').value.trim();
    const consumedAmount = parseFloat(document.getElementById('att-amount').value) || 0;
    if (!courseId) return showToast('请选择课程', 'error');
    if (!lessonDate) return showToast('请选择上课日期', 'error');

    let result;
    if (currentEditAttId) {
        result = await api('update_attendance', {
            id: currentEditAttId,
            course_id: courseId,
            lesson_date: lessonDate,
            status: status,
            class_name: className,
            campus: campus,
            teacher: teacher,
            subject_level1: subjectLevel1,
            subject_level2: subjectLevel2,
            class_time: classTime,
            consumed_amount: consumedAmount
        });
    } else {
        result = await api('add_attendance', {
            student_id: sid,
            course_id: courseId,
            lesson_date: lessonDate,
            status: status,
            class_name: className,
            campus: campus,
            teacher: teacher,
            subject_level1: subjectLevel1,
            subject_level2: subjectLevel2,
            class_time: classTime,
            consumed_amount: consumedAmount
        });
    }
    if (result.error) { showToast(result.error, 'error'); return; }
    showToast(result.message);
    closeModal('modal-attendance');
    loadAttendance(sid);
}

async function deleteAttendance(id) {
    showCustomConfirm('确定删除该上课记录？', async () => {
        const result = await api('delete_attendance', { id: id });
        if (result.error) { showToast(result.error, 'error'); return; }
        showToast(result.message);
        loadAttendance(currentViewStudentId);
    });
}

function switchToStudents() {
    activatePanel('panel-students');
    highlightLeafByPanel('panel-students');
    loadStudents();
}

// ==================== 报名详情页面 ====================
let currentEnrollStudentId = null;
let currentEnrollCourseId = null;
let currentEnrollPlanId = null;
let currentEnrollPlanType = '';
let currentEnrollPlans = [];
let currentEnrollMode = 'student'; // 'student' | 'resource'
let currentEnrollResourceId = null;
let currentEnrollCampusId = null;

async function goEnroll(studentId) {
    currentEnrollStudentId = studentId;
    currentEnrollCourseId = null;
    currentEnrollPlanId = null;
    currentEnrollPlanType = '';
    currentEnrollPlans = [];
    currentEnrollMode = 'student';
    currentEnrollResourceId = null;
    currentEnrollCampusId = null;

    // Reset labels for student mode
    document.getElementById('btn-enroll-back').textContent = '返回学员详情';

    // Load student info
    try {
        const res = await fetch(API_BASE + 'get_student&id=' + studentId);
        const data = await res.json();
        if (data.error) { showToast(data.error, 'error'); return; }
        document.getElementById('enroll-info-name').textContent = data.student.name || '-';
        document.getElementById('enroll-info-phone').textContent = data.student.phone || '-';
    } catch (e) { showToast('加载学员信息失败', 'error'); return; }

    // Reset course dropdown
    const courseSel = document.getElementById('enroll-course-select');
    courseSel.innerHTML = '<option value="">请先选择校区</option>';
    courseSel.disabled = true;

    // Load campus list
    await loadCampusOptions('enroll-campus-select');

    // Hide plan/items sections
    document.getElementById('enroll-plans-section').style.display = 'none';
    document.getElementById('enroll-items-section').style.display = 'none';

    activatePanel('panel-enroll');
    highlightLeafByPanel('panel-enroll');
}

async function goEnrollFromResource(resourceId) {
    currentEnrollMode = 'resource';
    currentEnrollResourceId = resourceId;
    currentEnrollStudentId = null;
    currentEnrollCourseId = null;
    currentEnrollPlanId = null;
    currentEnrollPlanType = '';
    currentEnrollPlans = [];
    currentEnrollCampusId = null;

    // Load resource info and display
    try {
        const res = await fetch(API_BASE + 'get_resources&page=1&page_size=1&resource_id=' + resourceId);
        const data = await res.json();
        if (!data.data || data.data.length === 0) { showToast('未找到该资源', 'error'); return; }
        const resource = data.data[0];
        document.getElementById('enroll-info-name').textContent = resource.name || '-';
        document.getElementById('enroll-info-phone').textContent = resource.phone || '-';
    } catch (e) { showToast('加载资源信息失败', 'error'); return; }

    // Reset course dropdown
    const courseSel = document.getElementById('enroll-course-select');
    courseSel.innerHTML = '<option value="">请先选择校区</option>';
    courseSel.disabled = true;

    // Load campus list
    await loadCampusOptions('enroll-campus-select');

    // Update back button text
    document.getElementById('btn-enroll-back').textContent = '返回我的资源';

    // Hide plan/items sections
    document.getElementById('enroll-plans-section').style.display = 'none';
    document.getElementById('enroll-items-section').style.display = 'none';

    activatePanel('panel-enroll');
    highlightLeafByPanel('panel-enroll');
}

// Campus select change → filter courses
document.addEventListener('DOMContentLoaded', function() {
    const campusSel = document.getElementById('enroll-campus-select');
    if (campusSel) {
        campusSel.addEventListener('change', async function() {
            const campusId = this.value;
            currentEnrollCampusId = campusId ? parseInt(campusId) : null;
            currentEnrollCourseId = null;
            currentEnrollPlanId = null;
            document.getElementById('enroll-plans-section').style.display = 'none';
            document.getElementById('enroll-items-section').style.display = 'none';
            const courseSel = document.getElementById('enroll-course-select');
            if (!campusId) {
                courseSel.innerHTML = '<option value="">请先选择校区</option>';
                courseSel.disabled = true;
                return;
            }
            await loadCourseOptionsByCampus('enroll-course-select', campusId);
            courseSel.disabled = false;
        });
    }

    const courseSel = document.getElementById('enroll-course-select');
    if (courseSel) {
        courseSel.addEventListener('change', async function() {
            const courseId = this.value;
            currentEnrollCourseId = courseId ? parseInt(courseId) : null;
            currentEnrollPlanId = null;
            document.getElementById('enroll-plans-section').style.display = 'none';
            document.getElementById('enroll-items-section').style.display = 'none';
            if (!courseId) return;

            document.getElementById('enroll-plans-section').style.display = 'block';
            const listDiv = document.getElementById('enroll-plans-list');
            listDiv.innerHTML = '<div style="color:#999;padding:12px;">加载中...</div>';

            try {
                const res = await fetch(API_BASE + 'get_course_plans&course_id=' + courseId);
                const data = await res.json();
                currentEnrollPlans = data.data || [];
                renderEnrollPlansList(currentEnrollPlans);
            } catch (e) {
                listDiv.innerHTML = '<div style="color:#e74c3c;padding:12px;">加载失败</div>';
            }
        });
    }

    // Back button
    const backBtn = document.getElementById('btn-enroll-back');
    if (backBtn) {
        backBtn.addEventListener('click', function() {
            if (currentEnrollStudentId) {
                viewStudent(currentEnrollStudentId);
            }
        });
    }
});

function renderEnrollPlansList(plans) {
    const listDiv = document.getElementById('enroll-plans-list');
    if (!plans || plans.length === 0) {
        listDiv.innerHTML = '<div style="color:#999;padding:12px;">该课程下暂无价格方案</div>';
        return;
    }
    listDiv.innerHTML = plans.map(p => {
        let typeTag = '';
        const pt = p.plan_type || '';
        if (pt === '新报') typeTag = '<span class="tag tag-new-enroll">新报</span>';
        else if (pt === '续费') typeTag = '<span class="tag tag-renewal">续费</span>';
        else if (pt === '小课包') typeTag = '<span class="tag tag-small-pack">小课包</span>';
        return `<div class="enroll-plan-card" data-plan-id="${p.id}" onclick="selectEnrollPlan(${p.id})">
            <span class="enroll-plan-name">${esc(p.name)}${typeTag}</span>
            <span class="enroll-plan-arrow">&gt;</span>
        </div>`;
    }).join('');
}

function selectEnrollPlan(planId) {
    currentEnrollPlanId = planId;
    currentEnrollPlanType = '';
    // Highlight the selected card
    document.querySelectorAll('.enroll-plan-card').forEach(c => c.classList.remove('active'));
    const card = document.querySelector(`.enroll-plan-card[data-plan-id="${planId}"]`);
    if (card) card.classList.add('active');

    const plan = currentEnrollPlans.find(p => p.id == planId);
    currentEnrollPlanType = (plan && plan.plan_type) ? plan.plan_type : '';
    if (!plan || !plan.items || plan.items.length === 0) {
        document.getElementById('enroll-items-section').style.display = 'none';
        return;
    }

    // Render items table
    const tbody = document.getElementById('enroll-items-tbody');
    let total = 0;
    tbody.innerHTML = plan.items.map(item => {
        const unitPrice = item.lesson_count > 0 ? (item.actual_price / item.lesson_count) : 0;
        total += parseFloat(item.actual_price) || 0;
        return `<tr>
            <td>${esc(item.name)}</td>
            <td class="col-num">${item.lesson_count || 0}</td>
            <td class="col-num">¥${unitPrice.toFixed(2)}</td>
            <td class="col-num">¥${Number(item.actual_price).toFixed(2)}</td>
        </tr>`;
    }).join('');
    document.getElementById('enroll-total-price').textContent = '¥' + total.toFixed(2);
    document.getElementById('enroll-items-section').style.display = 'block';

    // 初始化支付方式：现金默认显示总金额，美团显示0
    document.getElementById('enroll-payment-cash').value = total.toFixed(2);
    document.getElementById('enroll-payment-meituan').value = '0.00';
    updatePaymentHint();
}

// ==================== 支付方式交互 ====================
function onPaymentInput() {
    updatePaymentHint();
}

function updatePaymentHint() {
    const cash = parseFloat(document.getElementById('enroll-payment-cash').value) || 0;
    const meituan = parseFloat(document.getElementById('enroll-payment-meituan').value) || 0;
    const totalText = document.getElementById('enroll-total-price').textContent.replace('¥', '');
    const total = parseFloat(totalText) || 0;
    const hint = document.getElementById('enroll-payment-hint');
    const diff = cash + meituan - total;
    if (Math.abs(diff) < 0.01) {
        hint.style.display = 'block';
        hint.className = 'enroll-payment-hint ok';
        hint.textContent = '金额匹配' + (cash > 0 && meituan > 0 ? '（现金 ¥' + cash.toFixed(2) + ' + 美团 ¥' + meituan.toFixed(2) + '）' : '');
    } else if (diff > 0) {
        hint.style.display = 'block';
        hint.className = 'enroll-payment-hint warn';
        hint.textContent = '超出合计 ¥' + diff.toFixed(2) + '，请减少支付金额';
    } else {
        hint.style.display = 'block';
        hint.className = 'enroll-payment-hint warn';
        hint.textContent = '还差 ¥' + Math.abs(diff).toFixed(2) + '，请补足支付金额';
    }
}

async function confirmPayEnroll() {
    // 支付金额校验
    const paymentCash = parseFloat(document.getElementById('enroll-payment-cash').value) || 0;
    const paymentMeituan = parseFloat(document.getElementById('enroll-payment-meituan').value) || 0;
    const totalText = document.getElementById('enroll-total-price').textContent.replace('¥', '');
    const totalPrice = parseFloat(totalText) || 0;
    if (Math.abs(paymentCash + paymentMeituan - totalPrice) > 0.01) {
        return showToast('支付金额合计（¥' + (paymentCash + paymentMeituan).toFixed(2) + '）与订单总额（¥' + totalPrice.toFixed(2) + '）不一致，请调整', 'error');
    }

    if (currentEnrollMode === 'resource') {
        if (!currentEnrollResourceId) return showToast('资源信息缺失', 'error');
        if (!currentEnrollCourseId) return showToast('请选择课程', 'error');
        if (!currentEnrollPlanId) return showToast('请选择价格方案', 'error');

        showCustomConfirm('确认报名？系统将自动为该资源创建学员记录并生成订单。', async () => {
            // Step 1: Create student from resource
            const createResult = await api('create_student_from_resource', {
                resource_id: currentEnrollResourceId
            }, 'POST');
            if (createResult.error) { showToast(createResult.error, 'error'); return; }
            const studentId = createResult.student_id;

            // Step 2: Pay enroll
            const result = await api('pay_enroll', {
                student_id: studentId,
                course_id: currentEnrollCourseId,
                plan_id: currentEnrollPlanId,
                plan_type: currentEnrollPlanType,
                payment_cash: paymentCash,
                payment_meituan: paymentMeituan,
                campus_id: currentEnrollCampusId || 0
            }, 'POST');
            if (result.error) { showToast(result.error, 'error'); return; }
            showToast(result.message);
            // 跳转至交易订单列表
            activatePanel('panel-orders');
            highlightLeafByPanel('panel-orders');
            loadOrders();
        });
        return;
    }

    if (!currentEnrollStudentId) return showToast('学员信息缺失', 'error');
    if (!currentEnrollCourseId) return showToast('请选择课程', 'error');
    if (!currentEnrollPlanId) return showToast('请选择价格方案', 'error');

    showCustomConfirm('确认支付？将按照该方案下所有报价单逐条生成订单。', async () => {
        const result = await api('pay_enroll', {
            student_id: currentEnrollStudentId,
            course_id: currentEnrollCourseId,
            plan_id: currentEnrollPlanId,
            plan_type: currentEnrollPlanType,
            payment_cash: paymentCash,
            payment_meituan: paymentMeituan,
            campus_id: currentEnrollCampusId || 0
        }, 'POST');
        if (result.error) { showToast(result.error, 'error'); return; }
        showToast(result.message);
        // 跳转至交易订单列表
        activatePanel('panel-orders');
        highlightLeafByPanel('panel-orders');
        loadOrders();
    });
}

// ==================== 校区/课程 通用加载 ====================
async function loadCampusOptions(selectId) {
    const sel = document.getElementById(selectId);
    if (!sel) return;
    sel.innerHTML = '<option value="">请选择校区</option>';
    try {
        const res = await fetch(API_BASE + 'list_organizations');
        const data = await res.json();
        const campuses = [];
        if (data.data && data.data.flat) {
            data.data.flat.forEach(org => {
                if (org.type === '校区') campuses.push(org);
            });
        }
        campuses.sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0) || a.id - b.id);
        campuses.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.name;
            sel.appendChild(opt);
        });
    } catch (e) { /* ignore */ }
}

async function loadCourseOptionsByCampus(selectId, campusId) {
    const sel = document.getElementById(selectId);
    if (!sel) return;
    sel.innerHTML = '<option value="">加载中...</option>';
    try {
        const res = await fetch(API_BASE + 'list_courses&page=1&page_size=200&campus_id=' + campusId);
        const data = await res.json();
        sel.innerHTML = '<option value="">请选择课程</option>';
        if (data.data && data.data.length > 0) {
            data.data.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.name;
                sel.appendChild(opt);
            });
        }
    } catch (e) {
        sel.innerHTML = '<option value="">加载失败</option>';
    }
}

// ==================== 报名课程（旧弹窗保留） ====================
async function showEnrollModal(sid) {
    document.getElementById('enroll-student-id').value = sid;
    document.getElementById('enroll-campus').value = '';
    document.getElementById('enroll-course').value = '';
    document.getElementById('enroll-plan').innerHTML = '<option value="">请先选择课程</option>';
    document.getElementById('enroll-item').innerHTML = '<option value="">请先选择价格方案</option>';
    document.getElementById('enroll-detail').style.display = 'none';
    document.getElementById('enroll-lesson-count').textContent = '-';
    document.getElementById('enroll-actual-price').textContent = '-';
    // 加载校区列表
    await loadCampusOptions('enroll-campus');
    // 重置课程下拉
    const courseSel = document.getElementById('enroll-course');
    courseSel.innerHTML = '<option value="">请先选择校区</option>';
    courseSel.disabled = true;
    openModal('modal-enroll');
}

// 全局存储当前课程的价格方案数据
let enrollPricePlans = [];

// 校区选择 → 过滤课程
document.getElementById('enroll-campus').addEventListener('change', async function() {
    const campusId = this.value;
    const courseSel = document.getElementById('enroll-course');
    document.getElementById('enroll-plan').innerHTML = '<option value="">请先选择课程</option>';
    document.getElementById('enroll-item').innerHTML = '<option value="">请先选择价格方案</option>';
    document.getElementById('enroll-detail').style.display = 'none';
    enrollPricePlans = [];
    if (!campusId) {
        courseSel.innerHTML = '<option value="">请先选择校区</option>';
        courseSel.disabled = true;
        return;
    }
    await loadCourseOptionsByCampus('enroll-course', campusId);
    courseSel.disabled = false;
});

document.getElementById('enroll-course').addEventListener('change', async function() {
    const courseId = this.value;
    const planSel = document.getElementById('enroll-plan');
    const itemSel = document.getElementById('enroll-item');
    document.getElementById('enroll-detail').style.display = 'none';
    if (!courseId) {
        planSel.innerHTML = '<option value="">请先选择课程</option>';
        itemSel.innerHTML = '<option value="">请先选择价格方案</option>';
        enrollPricePlans = [];
        return;
    }
    planSel.innerHTML = '<option value="">加载中...</option>';
    itemSel.innerHTML = '<option value="">请先选择价格方案</option>';
    try {
        const res = await fetch(API_BASE + 'list_price_plans&course_id=' + courseId);
        const data = await res.json();
        enrollPricePlans = data.data || [];
        planSel.innerHTML = '<option value="">请选择价格方案</option>';
        enrollPricePlans.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = p.name;
            planSel.appendChild(opt);
        });
    } catch (e) {
        planSel.innerHTML = '<option value="">加载失败</option>';
        enrollPricePlans = [];
    }
});

document.getElementById('enroll-plan').addEventListener('change', function() {
    const planId = this.value;
    const itemSel = document.getElementById('enroll-item');
    document.getElementById('enroll-detail').style.display = 'none';
    if (!planId) {
        itemSel.innerHTML = '<option value="">请先选择价格方案</option>';
        return;
    }
    const plan = enrollPricePlans.find(p => p.id == planId);
    if (!plan || !plan.items || plan.items.length === 0) {
        itemSel.innerHTML = '<option value="">该方案下无报价单</option>';
        return;
    }
    itemSel.innerHTML = '<option value="">请选择报价单</option>';
    plan.items.forEach(item => {
        const opt = document.createElement('option');
        opt.value = JSON.stringify({ id: item.id, name: item.name, lesson_count: item.lesson_count, actual_price: item.actual_price, plan_name: plan.name });
        opt.textContent = item.name;
        itemSel.appendChild(opt);
    });
});

document.getElementById('enroll-item').addEventListener('change', function() {
    const val = this.value;
    const detail = document.getElementById('enroll-detail');
    if (!val) {
        detail.style.display = 'none';
        return;
    }
    try {
        const item = JSON.parse(val);
        document.getElementById('enroll-lesson-count').textContent = item.lesson_count || 0;
        document.getElementById('enroll-actual-price').textContent = '¥' + Number(item.actual_price || 0).toFixed(2);
        detail.style.display = 'block';
    } catch (e) {
        detail.style.display = 'none';
    }
});

async function confirmEnroll() {
    const studentId = document.getElementById('enroll-student-id').value;
    const courseId = document.getElementById('enroll-course').value;
    const itemVal = document.getElementById('enroll-item').value;
    const campusId = parseInt(document.getElementById('enroll-campus').value) || 0;
    if (!studentId || !courseId) return showToast('请先选择课程', 'error');
    if (!itemVal) return showToast('请选择报价单', 'error');
    let item;
    try { item = JSON.parse(itemVal); } catch (e) { return showToast('数据错误', 'error'); }
    const data = {
        student_id: parseInt(studentId),
        course_id: parseInt(courseId),
        plan_name: item.plan_name,
        item_name: item.name,
        lesson_count: item.lesson_count,
        actual_price: item.actual_price,
        campus_id: campusId
    };
    const result = await api('enroll_course', data);
    if (result.error) { showToast(result.error, 'error'); return; }
    showToast(result.message);
    closeModal('modal-enroll');
    // 刷新详情
    viewStudent(currentViewStudentId);
}




// ==================== 资源报名课程 ====================
let enrollResourcePricePlans = [];

async function showResourceEnrollModal(rid) {
    document.getElementById('enroll-resource-id').value = rid;
    document.getElementById('enroll-resource-campus').value = '';
    document.getElementById('enroll-resource-course').value = '';
    document.getElementById('enroll-resource-plan').innerHTML = '<option value="">请先选择课程</option>';
    document.getElementById('enroll-resource-item').innerHTML = '<option value="">请先选择价格方案</option>';
    document.getElementById('enroll-resource-detail').style.display = 'none';
    document.getElementById('enroll-resource-lesson-count').textContent = '-';
    document.getElementById('enroll-resource-actual-price').textContent = '-';
    // 加载校区列表
    await loadCampusOptions('enroll-resource-campus');
    // 重置课程下拉
    const courseSel = document.getElementById('enroll-resource-course');
    courseSel.innerHTML = '<option value="">请先选择校区</option>';
    courseSel.disabled = true;
    openModal('modal-resource-enroll');
}

// 校区选择 → 过滤课程
document.getElementById('enroll-resource-campus').addEventListener('change', async function() {
    const campusId = this.value;
    const courseSel = document.getElementById('enroll-resource-course');
    document.getElementById('enroll-resource-plan').innerHTML = '<option value="">请先选择课程</option>';
    document.getElementById('enroll-resource-item').innerHTML = '<option value="">请先选择价格方案</option>';
    document.getElementById('enroll-resource-detail').style.display = 'none';
    enrollResourcePricePlans = [];
    if (!campusId) {
        courseSel.innerHTML = '<option value="">请先选择校区</option>';
        courseSel.disabled = true;
        return;
    }
    await loadCourseOptionsByCampus('enroll-resource-course', campusId);
    courseSel.disabled = false;
});

document.getElementById('enroll-resource-course').addEventListener('change', async function() {
    const courseId = this.value;
    const planSel = document.getElementById('enroll-resource-plan');
    const itemSel = document.getElementById('enroll-resource-item');
    document.getElementById('enroll-resource-detail').style.display = 'none';
    if (!courseId) {
        planSel.innerHTML = '<option value="">请先选择课程</option>';
        itemSel.innerHTML = '<option value="">请先选择价格方案</option>';
        enrollResourcePricePlans = [];
        return;
    }
    planSel.innerHTML = '<option value="">加载中...</option>';
    itemSel.innerHTML = '<option value="">请先选择价格方案</option>';
    try {
        const res = await fetch(API_BASE + 'list_price_plans&course_id=' + courseId);
        const data = await res.json();
        enrollResourcePricePlans = data.data || [];
        planSel.innerHTML = '<option value="">请选择价格方案</option>';
        enrollResourcePricePlans.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = p.name;
            planSel.appendChild(opt);
        });
    } catch (e) {
        planSel.innerHTML = '<option value="">加载失败</option>';
        enrollResourcePricePlans = [];
    }
});

document.getElementById('enroll-resource-plan').addEventListener('change', function() {
    const planId = this.value;
    const itemSel = document.getElementById('enroll-resource-item');
    document.getElementById('enroll-resource-detail').style.display = 'none';
    if (!planId) {
        itemSel.innerHTML = '<option value="">请先选择价格方案</option>';
        return;
    }
    const plan = enrollResourcePricePlans.find(p => p.id == planId);
    if (!plan || !plan.items || plan.items.length === 0) {
        itemSel.innerHTML = '<option value="">该方案下无报价单</option>';
        return;
    }
    itemSel.innerHTML = '<option value="">请选择报价单</option>';
    plan.items.forEach(item => {
        const opt = document.createElement('option');
        opt.value = JSON.stringify({ id: item.id, name: item.name, lesson_count: item.lesson_count, actual_price: item.actual_price, plan_name: plan.name });
        opt.textContent = item.name;
        itemSel.appendChild(opt);
    });
});

document.getElementById('enroll-resource-item').addEventListener('change', function() {
    const val = this.value;
    const detail = document.getElementById('enroll-resource-detail');
    if (!val) {
        detail.style.display = 'none';
        return;
    }
    try {
        const item = JSON.parse(val);
        document.getElementById('enroll-resource-lesson-count').textContent = item.lesson_count || 0;
        document.getElementById('enroll-resource-actual-price').textContent = '¥' + Number(item.actual_price || 0).toFixed(2);
        detail.style.display = 'block';
    } catch (e) {
        detail.style.display = 'none';
    }
});

async function confirmResourceEnroll() {
    const resourceId = document.getElementById('enroll-resource-id').value;
    const courseId = document.getElementById('enroll-resource-course').value;
    const itemVal = document.getElementById('enroll-resource-item').value;
    const campusId = parseInt(document.getElementById('enroll-resource-campus').value) || 0;
    if (!resourceId || !courseId) return showToast('请先选择课程', 'error');
    if (!itemVal) return showToast('请选择报价单', 'error');
    let item;
    try { item = JSON.parse(itemVal); } catch (e) { return showToast('数据错误', 'error'); }
    const data = {
        resource_id: parseInt(resourceId),
        course_id: parseInt(courseId),
        plan_name: item.plan_name,
        item_name: item.name,
        lesson_count: item.lesson_count,
        actual_price: item.actual_price,
        campus_id: campusId
    };
    const result = await api('enroll_from_resource', data);
    if (result.error) { showToast(result.error, 'error'); return; }
    showToast(result.message);
    closeModal('modal-resource-enroll');
    loadMyResources();
}
// ==================== 交易订单 ====================
async function loadOrders() {
    const keyword = document.getElementById('search-order').value;
    const params = new URLSearchParams({ page: orderPage, page_size: 15 });
    if (keyword) params.set('keyword', keyword);
    const res = await fetch(API_BASE + 'list_orders&' + params);
    const data = await res.json();
    renderOrderTable(data.data);
    renderPagination('pagination-order', data.total, orderPage, 15, (p) => { orderPage = p; loadOrders(); });
    document.getElementById('stat-orders-inline').textContent = data.total || 0;
    renderPaymentSummary(data.payment_summary || []);
}

function renderOrderTable(rows) {
    const tbody = document.querySelector('#table-orders tbody');
    const tfoot = document.getElementById('table-orders-foot');
    if (!rows || rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="16" style="text-align:center;color:#999;padding:30px;">暂无订单数据</td></tr>';
        tfoot.style.display = 'none';
        return;
    }
    let totalCash = 0, totalMeituan = 0;
    tbody.innerHTML = rows.map(r => {
        const cash = Number(r.cash_amount) || 0;
        const mt = Number(r.meituan_amount) || 0;
        totalCash += cash;
        totalMeituan += mt;
        let orderTypeHtml = '';
        const ot = (r.order_type || '').trim();
        if (ot === '新报') orderTypeHtml = '<span class="tag tag-new-enroll">新报</span>';
        else if (ot === '续费') orderTypeHtml = '<span class="tag tag-renewal">续费</span>';
        else if (ot === '小课包') orderTypeHtml = '<span class="tag tag-small-pack">小课包</span>';
        return `
        <tr>
            <td style="font-family:monospace;font-size:12px;">${esc(r.order_no || '')}</td>
            <td style="font-family:monospace;font-size:12px;">${esc(r.parent_order_no || '')}</td>
            <td style="font-family:monospace;font-size:12px;">${esc(r.student_no || '')}</td>
            <td>${r.id}</td>
            <td>${esc(r.student_name)}</td>
            <td>${esc(r.campus || '-')}</td>
            <td>${esc(r.course_name)}</td>
            <td>${esc(r.plan_name)}</td>
            <td>${esc(r.item_name)}</td>
            <td>${r.lesson_count || ''}</td>
            <td>${r.actual_price != null ? '¥' + Number(r.actual_price).toFixed(2) : ''}</td>
            <td>${cash > 0 ? '¥' + cash.toFixed(2) : '-'}</td>
            <td>${mt > 0 ? '¥' + mt.toFixed(2) : '-'}</td>
            <td>${r.created_at ? r.created_at.slice(0, 16) : ''}</td>
            <td>${r.paid_at ? r.paid_at.slice(0, 16) : ''}</td>
            <td>${orderTypeHtml}</td>
        </tr>`;
    }).join('');
    tfoot.innerHTML = `<tr>
        <td colspan="12" style="text-align:right;font-weight:bold;">合计</td>
        <td style="font-weight:bold;color:#7c3aed;">¥${totalCash.toFixed(2)}</td>
        <td style="font-weight:bold;color:#7c3aed;">¥${totalMeituan.toFixed(2)}</td>
        <td colspan="2"></td>
    </tr>`;
    tfoot.style.display = '';
}

function renderPaymentSummary(summary) {
    const el = document.getElementById('payment-summary-order');
    if (!summary || (summary.cash_total === undefined && summary.meituan_total === undefined)) {
        el.style.display = 'none';
        return;
    }
    const cash = Number(summary.cash_total) || 0;
    const mt = Number(summary.meituan_total) || 0;
    const total = cash + mt;
    el.innerHTML = `<div class="payment-summary-inner">
        <span class="payment-summary-title">支付方式汇总</span>
        <div class="payment-summary-card">
            <span class="payment-summary-label">现金</span>
            <span class="payment-summary-amount">¥${cash.toFixed(2)}</span>
        </div>
        <div class="payment-summary-card">
            <span class="payment-summary-label">美团</span>
            <span class="payment-summary-amount">¥${mt.toFixed(2)}</span>
        </div>
        <div class="payment-summary-card payment-summary-total">
            <span class="payment-summary-label">总计</span>
            <span class="payment-summary-amount">¥${total.toFixed(2)}</span>
        </div>
    </div>`;
    el.style.display = 'block';
}


// ==================== 班级详情 ====================
function viewClassDetail(classId, className) {
    currentClassDetailId = classId;
    document.getElementById('class-detail-name').textContent = className || '班级详情';
    activatePanel('panel-class-detail');
    highlightLeafByPanel('panel-classes');
    switchClassDetailTab('tab-class-students');
}

function switchToClasses() {
    activatePanel('panel-classes');
    highlightLeafByPanel('panel-classes');
    loadClasses();
}

function switchClassDetailTab(tabId) {
    document.querySelectorAll('.cdt-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === tabId));
    document.querySelectorAll('.cdt-panel').forEach(p => p.classList.toggle('active', p.id === tabId));
    if (!currentClassDetailId) return;
    if (tabId === 'tab-class-students') loadClassStudents();
    else if (tabId === 'tab-class-schedules') loadClassSchedules();
}

// 标签页点击事件 (cdt-tab)
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('cdt-tab')) {
        switchClassDetailTab(e.target.dataset.tab);
    }
    if (e.target.classList.contains('att-tab')) {
        switchAttendanceTab(e.target.dataset.tab);
    }
});

function switchAttendanceTab(tabId) {
    document.querySelectorAll('.att-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === tabId));
    document.querySelectorAll('.att-panel').forEach(p => p.classList.toggle('active', p.id === tabId));
    if (tabId === 'tab-attendance-operations') {
        loadAttendanceSessions();
    } else if (tabId === 'tab-student-consumption') {
        loadStudentConsumption();
    }
}

// ==================== 班级详情 - 学员列表 ====================
async function loadClassStudents() {
    const tbody = document.querySelector('#table-class-students tbody');
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#999;padding:20px;">加载中...</td></tr>';
    try {
        const res = await fetch(API_BASE + 'list_class_students&class_id=' + currentClassDetailId);
        const data = await res.json();
        const rows = data.data || [];
        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#999;padding:30px;">暂无学员</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(r => `<tr>
            <td style="font-family:monospace;font-size:12px;">${esc(r.student_no || '')}</td>
            <td>${esc(r.name)}</td>
            <td>${esc(r.phone)}</td>
            <td>${esc(r.source)}</td>
            <td>
                <button class="btn-link-danger" onclick="removeStudentFromClass(${r.cs_id}, '${esc(r.name).replace(/'/g, "\\'")}')">移出</button>
            </td>
        </tr>`).join('');
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#e74c3c;padding:20px;">加载失败</td></tr>';
    }
}

async function showAddStudentModal() {
    if (!currentClassDetailId) return showToast('班级信息缺失', 'error');
    document.getElementById('modal-add-student-title').textContent = '添加学员';
    document.getElementById('add-student-search').value = '';
    openModal('modal-add-student');
    await searchAvailableStudents();
}

async function searchAvailableStudents() {
    const tbody = document.getElementById('available-students-tbody');
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#999;padding:20px;">加载中...</td></tr>';
    const keyword = document.getElementById('add-student-search').value.trim();
    try {
        let url = API_BASE + 'get_available_students&class_id=' + currentClassDetailId;
        if (keyword) url += '&keyword=' + encodeURIComponent(keyword);
        const res = await fetch(url);
        const data = await res.json();
        const rows = data.data || [];
        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#999;padding:20px;">暂无可选学员</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(r => `<tr>
            <td style="padding:8px;border-bottom:1px solid #f5f5f5;font-family:monospace;font-size:12px;">${esc(r.student_no || '')}</td>
            <td style="padding:8px;border-bottom:1px solid #f5f5f5;">${esc(r.name)}</td>
            <td style="padding:8px;border-bottom:1px solid #f5f5f5;">${esc(r.phone)}</td>
            <td style="padding:8px;border-bottom:1px solid #f5f5f5;">${r.remaining_hours || 0}</td>
            <td style="padding:8px;border-bottom:1px solid #f5f5f5;text-align:center;">
                <button class="btn btn-sm btn-primary" onclick="addStudentToClass(${r.id}, '${esc(r.name).replace(/'/g, "\\'")}')">加入</button>
            </td>
        </tr>`).join('');
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#e74c3c;padding:20px;">加载失败</td></tr>';
    }
}

async function addStudentToClass(studentId, studentName) {
    const result = await api('add_class_student', {
        class_id: currentClassDetailId,
        student_id: studentId
    });
    if (result.error) { showToast(result.error, 'error'); return; }
    showToast(result.message);
    // 刷新可选列表和学员列表
    searchAvailableStudents();
    loadClassStudents();
}

async function removeStudentFromClass(csId, studentName) {
    showCustomConfirm(`确定将学员「${studentName}」移出此班级？`, async () => {
        const result = await api('remove_class_student', { id: csId });
        if (result.error) { showToast(result.error, 'error'); return; }
        showToast(result.message);
        loadClassStudents();
    });
}

let availableStudentTimer = null;
function debounceSearchAvailableStudent() {
    if (availableStudentTimer) clearTimeout(availableStudentTimer);
    availableStudentTimer = setTimeout(() => searchAvailableStudents(), 300);
}

// ==================== 班级详情 - 编辑排课 ====================
async function loadClassSchedules() {
    const tbody = document.querySelector('#table-class-schedules tbody');
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#999;padding:20px;">加载中...</td></tr>';
    try {
        const res = await fetch(API_BASE + 'list_schedules&class_id=' + currentClassDetailId);
        const data = await res.json();
        const rows = data.data || [];
        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#999;padding:30px;">暂无排课记录</td></tr>';
            return;
        }
        let allSessions = [];
        rows.forEach(r => {
            const sessions = r.sessions || [];
            sessions.forEach((s, idx) => {
                allSessions.push({
                    scheduleId: r.id,
                    classId: r.class_id,
                    seq: allSessions.length + 1,
                    date: s.date || '',
                    dayOfWeek: s.dayOfWeek || '',
                    start: s.start || '',
                    end: s.end || '',
                    teacher: r.teacher || '',
                    classroom: r.classroom || ''
                });
            });
        });
        if (allSessions.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#999;padding:30px;">排课规则已添加，但未生成具体课次</td></tr>';
            return;
        }
        // 按日期排序
        allSessions.sort((a, b) => (a.date || '').localeCompare(b.date || ''));
        // 重新编号
        allSessions.forEach((s, i) => { s.seq = i + 1; });
        // 批量查询考勤状态
        const attStatusMap = {};
        for (const s of allSessions) {
            try {
                const r = await fetch(API_BASE + 'get_class_attendance&class_id=' + s.classId + '&schedule_id=' + s.scheduleId + '&session_date=' + s.date);
                const d = await r.json();
                const records = d.data || [];
                const hasAttended = records.some(rec => rec.status !== '');
                attStatusMap[s.scheduleId + '_' + s.date] = hasAttended;
            } catch (e) { attStatusMap[s.scheduleId + '_' + s.date] = false; }
        }
        tbody.innerHTML = allSessions.map(s => {
            const timeStr = (s.start && s.end) ? (s.start + '-' + s.end) : '-';
            const key = s.scheduleId + '_' + s.date;
            const attLabel = attStatusMap[key]
                ? '<span style="color:#27ae60;font-size:12px;">已考勤</span>'
                : '<span style="color:#999;font-size:12px;">未考勤</span>';
            return `<tr>
                <td>${s.seq}</td>
                <td>${s.date}</td>
                <td>${s.dayOfWeek}</td>
                <td>${timeStr}</td>
                <td>${esc(s.teacher)}</td>
                <td>${esc(s.classroom)}</td>
                <td>${attLabel}</td>
                <td>
                    <div class="action-btns">
                        <button class="btn-link" onclick="showClassAttendanceModal(${s.classId}, ${s.scheduleId}, '${s.date}', '${s.date} ${timeStr}')">考勤</button>
                        <button class="btn-link" onclick="editScheduleFromDetail(${s.scheduleId})">编辑</button>
                        <button class="btn-link-danger" onclick="deleteScheduleFromDetail(${s.scheduleId})">删除</button>
                    </div>
                </td>
            </tr>`;
        }).join('');
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#e74c3c;padding:20px;">加载失败</td></tr>';
    }
}

async function editScheduleFromDetail(scheduleId) {
    await showScheduleForm(currentClassDetailId, scheduleId);
}

async function deleteScheduleFromDetail(id) {
    showCustomConfirm('确定删除该排课记录？', async () => {
        const result = await api('delete_schedule', { id: id });
        if (result.error) { showToast(result.error, 'error'); return; }
        showToast(result.message);
        loadClassSchedules();
    });
}

// ==================== 分班弹窗 ====================

async function showClassEnrollModal(studentId) {
    currentEnrollStudentId = studentId;
    document.getElementById('modal-class-enroll-title').textContent = '分班';
    const tbody = document.getElementById('class-enroll-tbody');
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#999;padding:20px;">加载中...</td></tr>';
    openModal('modal-class-enroll');
    // 加载所有班级
    try {
        const res = await fetch(API_BASE + 'list_classes&page=1&page_size=500&has_schedule=1');
        const data = await res.json();
        const classes = data.data || [];
        if (classes.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#999;padding:30px;">暂无班级</td></tr>';
            return;
        }
        // 批量查询可分入状态
        const rows = [];
        for (const c of classes) {
            let enrollable = false;
            let remaining = 0;
            try {
                const r2 = await fetch(API_BASE + 'get_class_enrollable&class_id=' + c.id + '&student_id=' + studentId);
                const d2 = await r2.json();
                enrollable = !!d2.enrollable;
                remaining = d2.remaining_lessons || 0;
            } catch (e) { /* ignore */ }
            rows.push({ ...c, enrollable, remaining });
        }
        tbody.innerHTML = rows.map(c => {
            const btnHtml = c.enrollable
                ? `<button class="btn btn-sm btn-primary" onclick="enrollStudentToClass(${c.id}, '${esc(c.name).replace(/'/g, "\\'")}')">分班</button>`
                : '<span style="color:#e74c3c;font-size:12px;">不可分入</span>';
            return `<tr>
                <td>${esc(c.name)}</td>
                <td>${esc(c.course_name || '')}</td>
                <td>${esc(c.class_type || '')}</td>
                <td>${esc(c.campus || '')}</td>
                <td>${c.enrollable ? '<span style="color:#27ae60;">可分入</span>（剩余课时：' + c.remaining + '）' : '<span style="color:#e74c3c;">不可分入</span>'}</td>
                <td>${btnHtml}</td>
            </tr>`;
        }).join('');
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#e74c3c;padding:20px;">加载失败</td></tr>';
    }
}

async function enrollStudentToClass(classId, className) {
    const result = await api('add_class_student', {
        class_id: classId,
        student_id: currentEnrollStudentId
    });
    if (result.error) { showToast(result.error, 'error'); return; }
    showToast(result.message);
    // 刷新分班弹窗
    showClassEnrollModal(currentEnrollStudentId);
}

// ==================== 班级考勤 ====================
async function showClassAttendanceModal(classId, scheduleId, sessionDate, titleStr) {
    const today = new Date().toISOString().slice(0, 10);
    if (sessionDate > today) { showToast('未到考勤时间', 'error'); return; }
    document.getElementById('modal-class-attendance-title').textContent = '课次考勤 - ' + titleStr;
    document.getElementById('ca-class-id').value = classId;
    document.getElementById('ca-schedule-id').value = scheduleId;
    document.getElementById('ca-session-date').value = sessionDate;
    const tbody = document.getElementById('ca-attendance-tbody');
    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:#999;padding:20px;">加载中...</td></tr>';
    openModal('modal-class-attendance');
    try {
        const res = await fetch(API_BASE + 'get_class_attendance&class_id=' + classId + '&schedule_id=' + scheduleId + '&session_date=' + sessionDate);
        const data = await res.json();
        const rows = data.data || [];
        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:#999;padding:30px;">暂无学员</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(r => {
            const currentStatus = r.status || '出勤';
            return `<tr>
                <td>${esc(r.student_no)}</td>
                <td>${esc(r.student_name)}</td>
                <td><select class="ca-status-select" data-sid="${r.student_id}">
                    <option value="出勤" ${currentStatus === '出勤' ? 'selected' : ''}>出勤</option>
                    <option value="请假" ${currentStatus === '请假' ? 'selected' : ''}>请假</option>
                    <option value="缺勤" ${currentStatus === '缺勤' ? 'selected' : ''}>缺勤</option>
                </select></td>
                <td class="ca-deducted-display" data-sid="${r.student_id}" data-lesson-hours="${r.lesson_hours || 1}">${currentStatus === '出勤' ? '扣' + (r.lesson_hours || 1) + '课时' : '0'}</td>
            </tr>`;
        }).join('');
        // 绑定状态变更事件
        document.querySelectorAll('.ca-status-select').forEach(sel => {
            sel.addEventListener('change', function() {
                const sidAttr = this.dataset.sid;
                const display = document.querySelector('.ca-deducted-display[data-sid="' + sidAttr + '"]');
                if (display) {
                    const lh = parseInt(display.dataset.lessonHours) || 1;
                    display.textContent = this.value === '出勤' ? '扣' + lh + '课时' : '0';
                }
            });
        });
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:#e74c3c;padding:20px;">加载失败</td></tr>';
    }
}

async function saveClassAttendance() {
    const classId = parseInt(document.getElementById('ca-class-id').value);
    const scheduleId = parseInt(document.getElementById('ca-schedule-id').value);
    const sessionDate = document.getElementById('ca-session-date').value;
    const now = new Date();
    const curYM = now.getFullYear() * 100 + (now.getMonth() + 1);
    const sesYM = parseInt(sessionDate.slice(0, 7).replace('-', ''));
    if (sesYM < curYM) { showToast('不可修改之前月份的考勤', 'error'); return; }
    const records = [];
    document.querySelectorAll('.ca-status-select').forEach(sel => {
        records.push({
            student_id: parseInt(sel.dataset.sid),
            status: sel.value
        });
    });
    if (records.length === 0) { showToast('无学员可考勤', 'error'); return; }
    const result = await api('save_class_attendance', {
        class_id: classId,
        schedule_id: scheduleId,
        session_date: sessionDate,
        records: records
    });
    if (result.error) { showToast(result.error, 'error'); return; }
    showToast(result.message || '考勤保存成功');
    closeModal('modal-class-attendance');
    loadClassSchedules();
}

// ==================== 考勤（按课次） ====================

let attendanceSessionPage = 1;
let currentAttendanceClassId = 0;
let currentAttendanceScheduleId = 0;
let currentAttendanceSessionDate = '';

async function loadAttendanceSessions(page = 1) {
    attendanceSessionPage = page;
    const dateFromEl = document.getElementById('attendance-date-from');
    const dateToEl = document.getElementById('attendance-date-to');
    // 默认展示当天课次
    if (!dateFromEl.value && !dateToEl.value) {
        const today = new Date().toISOString().split('T')[0];
        dateFromEl.value = today;
        dateToEl.value = today;
    }
    const tbody = document.querySelector('#table-attendance-sessions tbody');
    const pagination = document.getElementById('pagination-attendance-sessions');
    const dateFrom = dateFromEl.value;
    const dateTo = dateToEl.value;
    tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;color:#999;padding:30px;">加载中...</td></tr>';
    try {
        let url = API_BASE + 'list_attendance_sessions&page=' + page + '&page_size=20';
        if (dateFrom) url += '&date_from=' + encodeURIComponent(dateFrom);
        if (dateTo) url += '&date_to=' + encodeURIComponent(dateTo);
        const res = await fetch(url);
        const data = await res.json();
        const sessions = data.data || [];
        const total = data.total || 0;
        if (sessions.length === 0) {
            tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;color:#999;padding:30px;">暂无排课记录</td></tr>';
            pagination.innerHTML = '';
            return;
        }
        // 批量查询考勤状态
        const attStatusMap = {};
        for (const s of sessions) {
            try {
                const r = await fetch(API_BASE + 'get_class_attendance&class_id=' + s.class_id + '&schedule_id=' + s.schedule_id + '&session_date=' + s.session_date);
                const d = await r.json();
                const records = d.data || [];
                attStatusMap[s.schedule_id + '_' + s.session_date] = records.some(rec => rec.status !== '');
            } catch (e) { attStatusMap[s.schedule_id + '_' + s.session_date] = false; }
        }
        tbody.innerHTML = sessions.map(s => {
            const key = s.schedule_id + '_' + s.session_date;
            const attLabel = attStatusMap[key]
                ? '<span style="color:#27ae60;font-size:12px;">已考勤</span>'
                : '<span style="color:#e67e22;font-size:12px;">未考勤</span>';
            const timeStr = (s.start_time && s.end_time) ? (s.start_time + '~' + s.end_time) : '-';
            return `<tr>
                <td>${s.session_date}</td>
                <td>${s.day_of_week}</td>
                <td>${esc(s.class_name)}</td>
                <td>${esc(s.course_name)}</td>
                <td>${timeStr}</td>
                <td>${esc(s.teacher)}</td>
                <td>${esc(s.classroom)}</td>
                <td>${esc(s.campus)}</td>
                <td>${attLabel}</td>
                <td><button class="btn-link" onclick="showAttendanceSessionModal(${s.class_id}, ${s.schedule_id}, '${s.session_date}', '${escJs(s.class_name)}', '${escJs(s.campus)}', '${escJs(s.course_name)}', '${escJs(s.teacher)}', '${escJs(s.classroom)}', '${s.session_date}', '${s.day_of_week}', '${timeStr}')">考勤</button></td>
            </tr>`;
        }).join('');
        // 分页
        const totalPages = Math.ceil(total / 20);
        pagination.innerHTML = totalPages > 1
            ? '<button class="btn btn-sm btn-outline" ' + (page <= 1 ? 'disabled' : 'onclick="loadAttendanceSessions(' + (page - 1) + ')"') + '>上一页</button>'
              + '<span style="margin:0 10px;">' + page + ' / ' + totalPages + '</span>'
              + '<button class="btn btn-sm btn-outline" ' + (page >= totalPages ? 'disabled' : 'onclick="loadAttendanceSessions(' + (page + 1) + ')"') + '>下一页</button>'
            : '';
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#e74c3c;padding:20px;">加载失败</td></tr>';
    }
}

// ==================== 考勤 - 学员课耗 ====================
async function loadStudentConsumption(page = 1) {
    const tbody = document.getElementById('consumption-tbody');
    const pagination = document.getElementById('pagination-student-consumption');
    const dateFrom = document.getElementById('consumption-date-from').value;
    const dateTo = document.getElementById('consumption-date-to').value;
    tbody.innerHTML = '<tr><td colspan="15" style="text-align:center;color:#999;padding:20px;">加载中...</td></tr>';
    try {
        let url = API_BASE + 'list_all_attendance&page=' + page + '&page_size=20';
        if (dateFrom) url += '&date_from=' + encodeURIComponent(dateFrom);
        if (dateTo) url += '&date_to=' + encodeURIComponent(dateTo);
        const res = await fetch(url);
        const data = await res.json();
        const rows = data.data || [];
        const total = data.total || 0;
        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="15" style="text-align:center;color:#999;padding:30px;">暂无考勤记录</td></tr>';
            pagination.innerHTML = '';
            return;
        }
        tbody.innerHTML = rows.map(r => {
            let statusClass = 'status-出勤';
            if (r.status === '缺勤') statusClass = 'status-缺勤';
            else if (r.status === '请假') statusClass = 'status-请假';
            return `<tr>
                <td>${esc(r.campus)}</td>
                <td>${esc(r.student_no)}</td>
                <td>${esc(r.student_name)}</td>
                <td>${esc(r.phone)}</td>
                <td>${esc(r.course_name)}</td>
                <td>${esc(r.subject_level1)}</td>
                <td>${esc(r.subject_level2)}</td>
                <td>${esc(r.class_name)}</td>
                <td>${esc(r.teacher)}</td>
                <td>${r.lesson_date}</td>
                <td>${esc(r.class_time)}</td>
                <td>${r.attended_at ? r.attended_at.slice(0, 19) : ''}</td>
                <td><span class="status-tag ${statusClass}">${esc(r.status)}</span></td>
                <td>${r.deducted_lessons || 0}</td>
                <td>¥${(parseFloat(r.consumed_amount) || 0).toFixed(2)}</td>
            </tr>`;
        }).join('');
        const totalPages = Math.ceil(total / 20);
        pagination.innerHTML = totalPages > 1
            ? '<button class="btn btn-sm btn-outline" ' + (page <= 1 ? 'disabled' : 'onclick="loadStudentConsumption(' + (page - 1) + ')"') + '>上一页</button>'
              + '<span style="margin:0 10px;">' + page + ' / ' + totalPages + '</span>'
              + '<button class="btn btn-sm btn-outline" ' + (page >= totalPages ? 'disabled' : 'onclick="loadStudentConsumption(' + (page + 1) + ')"') + '>下一页</button>'
            : '';
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="15" style="text-align:center;color:#e74c3c;padding:20px;">加载失败</td></tr>';
    }
}

function escJs(str) {
    if (!str) return '';
    return str.replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"');
}

async function showAttendanceSessionModal(classId, scheduleId, sessionDate, className, campus, courseName, teacher, classroom, sessionDateDisplay, dayOfWeek, timeStr) {
    const today = new Date().toISOString().slice(0, 10);
    if (sessionDate > today) { showToast('未到考勤时间', 'error'); return; }
    currentAttendanceClassId = classId;
    currentAttendanceScheduleId = scheduleId;
    currentAttendanceSessionDate = sessionDate;
    document.getElementById('as-subtitle').textContent = className + ' · ' + sessionDateDisplay;
    document.getElementById('as-class-name').textContent = className;
    document.getElementById('as-campus').textContent = campus;
    document.getElementById('as-session-date').textContent = sessionDateDisplay;
    document.getElementById('as-teacher').textContent = teacher;
    document.getElementById('as-course-name').textContent = courseName;
    document.getElementById('as-classroom').textContent = classroom;
    document.getElementById('as-time').textContent = dayOfWeek + ' ' + timeStr;
    const tbody = document.getElementById('as-attendance-tbody');
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--color-text-muted);padding:28px;">加载中...</td></tr>';
    openModal('modal-attendance-session');
    try {
        const res = await fetch(API_BASE + 'get_class_attendance&class_id=' + classId + '&schedule_id=' + scheduleId + '&session_date=' + sessionDate);
        const data = await res.json();
        const rows = data.data || [];
        document.getElementById('as-total-count').textContent = rows.length;
        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--color-text-muted);padding:36px;">该班级暂无学员</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(r => {
            const currentStatus = r.status || '';
            const maxDeductible = r.max_deductible || 0;
            let deducted = r.attendance_id ? (r.deducted_lessons || 0) : 0;
            // 已扣值不得超过一级学科剩余课时上限
            if (maxDeductible > 0 && deducted > maxDeductible) deducted = maxDeductible;
            const remaining = r.remaining_lessons || 0;
            return `<tr id="att-row-${r.student_id}" data-cs-id="${r.cs_id}">
                <td><button class="btn-remove-att" onclick="removeAttendanceStudent(${r.student_id}, ${r.cs_id})" title="移除此学员">移除</button></td>
                <td><span style="font-weight:500;">${esc(r.student_name)}</span></td>
                <td><span style="color:var(--color-text-secondary);">${esc(r.course_name || courseName)}</span></td>
                <td style="text-align:center;"><span style="font-weight:600;color:${remaining > 0 ? 'var(--color-success)' : 'var(--color-danger)'}">${remaining}</span></td>
                <td>
                    <span class="att-deduct-stepper">
                        <button class="stepper-btn" onclick="attDeductChange(this, -1)">−</button>
                        <span class="stepper-val" data-sid="${r.student_id}" data-lesson-hours="${r.lesson_hours || 1}" data-max="${maxDeductible}">${deducted}</span>
                        <button class="stepper-btn" onclick="attDeductChange(this, 1)">+</button>
                    </span>
                </td>
                <td>
                    <span class="att-status-group" data-sid="${r.student_id}">
                        <span class="att-status-chip ${currentStatus === '出勤' ? 'active' : ''}" data-val="出勤" onclick="attStatusToggle(this, '出勤')">出勤</span>
                        <span class="att-status-chip ${currentStatus === '缺勤' ? 'active' : ''}" data-val="缺勤" onclick="attStatusToggle(this, '缺勤')">缺勤</span>
                    </span>
                </td>
            </tr>`;
        }).join('');
        // 初始化缺勤状态或未考勤状态的步进器（禁用并置0）
        document.querySelectorAll('#as-attendance-tbody tr').forEach(row => {
            const activeChip = row.querySelector('.att-status-chip.active');
            if (activeChip) return; // 已选出勤，步进器启用，跳过
            const stepper = row.querySelector('.att-deduct-stepper');
            const valSpan = stepper ? stepper.querySelector('.stepper-val') : null;
            if (stepper && valSpan) {
                valSpan.textContent = '0';
                stepper.classList.add('disabled');
            }
        });
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--color-danger);padding:20px;">加载失败</td></tr>';
    }
}

async function removeAttendanceStudent(studentId, csId) {
    if (!csId || csId <= 0) return;
    const result = await api('remove_class_student', { id: csId, session_date: currentAttendanceSessionDate });
    if (result.error) { showToast(result.error, 'error'); return; }
    const row = document.getElementById('att-row-' + studentId);
    if (row) {
        row.style.transition = 'all 0.25s ease';
        row.style.opacity = '0';
        row.style.transform = 'translateX(-20px)';
        setTimeout(() => {
            row.remove();
            const tbody = document.getElementById('as-attendance-tbody');
            document.getElementById('as-total-count').textContent = tbody.querySelectorAll('tr').length;
        }, 250);
    }
}

function attDeductChange(btn, delta) {
    const stepper = btn.closest('.att-deduct-stepper');
    if (stepper.classList.contains('disabled')) return;
    const valSpan = stepper.querySelector('.stepper-val');
    let val = parseInt(valSpan.textContent) || 0;
    const max = parseInt(valSpan.dataset.max) || 0;
    val = Math.max(0, val + delta * 2);
    if (max > 0 && val > max) val = max;
    valSpan.textContent = val;
}

function attStatusToggle(el, status) {
    const group = el.closest('.att-status-group');
    group.querySelectorAll('.att-status-chip').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    const row = el.closest('tr');
    const stepper = row.querySelector('.att-deduct-stepper');
    const valSpan = stepper.querySelector('.stepper-val');
    if (status === '缺勤') {
        valSpan.textContent = '0';
        stepper.classList.add('disabled');
    } else {
        stepper.classList.remove('disabled');
        let lh = parseInt(valSpan.dataset.lessonHours) || 2;
        const max = parseInt(valSpan.dataset.max) || 0;
        if (max > 0 && lh > max) lh = max;
        valSpan.textContent = lh;
    }
}

async function saveAttendanceSession() {
    const now = new Date();
    const curYM = now.getFullYear() * 100 + (now.getMonth() + 1);
    const sesYM = parseInt(currentAttendanceSessionDate.slice(0, 7).replace('-', ''));
    if (sesYM < curYM) { showToast('不可修改之前月份的考勤', 'error'); return; }
    const records = [];
    const tbody = document.getElementById('as-attendance-tbody');
    const rows = tbody.querySelectorAll('tr');
    rows.forEach(row => {
        const stepperVal = row.querySelector('.stepper-val');
        const activeTag = row.querySelector('.att-status-chip.active');
        if (!stepperVal || !activeTag) return;
        const studentId = parseInt(stepperVal.dataset.sid);
        const deducted = parseInt(stepperVal.textContent) || 0;
        const status = activeTag.dataset.val;
        records.push({ student_id: studentId, status: status, deducted_lessons: deducted });
    });
    if (records.length === 0) { showToast('无学员可考勤', 'error'); return; }
    const result = await api('save_class_attendance', {
        class_id: currentAttendanceClassId,
        schedule_id: currentAttendanceScheduleId,
        session_date: currentAttendanceSessionDate,
        records: records
    });
    if (result.error) { showToast(result.error, 'error'); return; }
    showToast(result.message || '考勤保存成功');
    closeModal('modal-attendance-session');
    loadAttendanceSessions(attendanceSessionPage);
}

function addTempStudent() {
    showToast('添加临时学员功能开发中');
}

function addMakeupStudent() {
    showToast('添加补课学员功能开发中');
}
