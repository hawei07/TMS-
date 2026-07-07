// ==================== 全局状态 ====================
const API_BASE = '?action=';
let myPage = 1, aptPage = 1, seaPage = 1, empPage = 1, coursePage = 1, studentPage = 1, orderPage = 1, refundPage = 1;
let myPageSize = 15;
let myFilterTimer = null;
let searchTimers = {};
let commResourceId = null;
let commResourceName = '';
let currentBasicTypeCategory = 'course_type';
let currentClassDetailId = null;
let orderCampusData = []; // 订单校区筛选数据 [{name, region}]
let campusList = [];          // 充值弹窗校区下拉 [{id, name}]
let subjectLevel1List = [];   // 充值弹窗一级学科下拉 [{id, name}]

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
    initMyResourcesPanel();

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
        case 'panel-courses': loadFilterSubjects(); loadCourses(); break;
        case 'panel-subjects': loadSubjects(); break;
        case 'panel-classes': currentClassDetailId = null; loadClasses(); break;
        case 'panel-classrooms': loadClassrooms(); break;
        case 'panel-students': initStudentCampusFilter(); loadStudents(); break;
        case 'panel-orders': initOrderCampusFilter(); loadOrders(); break;
        case 'panel-attendance': switchAttendanceTab('tab-schedule-view'); break;
        case 'panel-work-records': initRefundCampusFilter(); initWorkRecordTabs(); loadRefundRecords(); break;
        case 'panel-cashflow': initCashflowDateRange(); loadCashflow(); break;
        case 'panel-revenue': initRevenueDateRange(); loadRevenue(); break;
        case 'panel-period-settings': loadPeriodTable(); break;
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
    const res = await api('list_channels', null, 'GET');
    return res.data || [];
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

// ==================== 上课时段管理 ====================
async function loadPeriodTable() {
    try {
        // 加载校区列表填充下拉框
        try {
            const cr = await fetch(API_BASE + 'list_campuses');
            const cd = await cr.json();
            const sel = document.getElementById('period-campus-input');
            if (sel && cd.data) {
                sel.innerHTML = '<option value="">选择校区</option>' + cd.data.map(c => `<option value="${esc(c.name)}">${esc(c.name)}</option>`).join('');
            }
        } catch(e) {}
        const res = await fetch(API_BASE + 'list_class_periods');
        const data = await res.json();
        const periods = data.data || [];
        const tbody = document.querySelector('#table-periods tbody');
        if (periods.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#999;padding:30px;">暂无时段，请添加</td></tr>';
            return;
        }
        tbody.innerHTML = periods.map(p => `
            <tr>
                <td>${p.sort_order || 0}</td>
                <td>${esc(p.name)}</td>
                <td>${p.start_time || ''}</td>
                <td>${p.end_time || ''}</td>
                <td>${esc(p.campus || '')}</td>
                <td>${p.created_at ? p.created_at.slice(0, 16) : ''}</td>
                <td><button class="btn-link-danger" onclick="deletePeriod(${p.id})">删除</button></td>
            </tr>
        `).join('');
    } catch (e) {
        console.error('loadPeriodTable', e);
        showToast('加载时段列表失败', 'error');
    }
}

async function addPeriod() {
    const nameEl = document.getElementById('period-name-input');
    const startEl = document.getElementById('period-start-input');
    const endEl = document.getElementById('period-end-input');
    const campusEl = document.getElementById('period-campus-input');
    const sortEl = document.getElementById('period-sort-input');
    const name = nameEl.value.trim();
    const start_time = startEl.value;
    const end_time = endEl.value;
    const campus = campusEl.value;
    const sort_order = parseInt(sortEl.value) || 0;

    if (!name) return showToast('请输入时段名称', 'error');
    if (!campus) return showToast('请选择校区', 'error');
    if (!start_time) return showToast('请选择开始时间', 'error');
    if (!end_time) return showToast('请选择结束时间', 'error');
    if (start_time >= end_time) return showToast('开始时间必须早于结束时间', 'error');

    try {
        const result = await api('add_class_period', { name, start_time, end_time, campus, sort_order });
        if (result.error) { showToast(result.error, 'error'); return; }
        showToast(result.message || '添加成功');
        nameEl.value = '';
        startEl.value = '';
        endEl.value = '';
        sortEl.value = '';
        loadPeriodTable();
    } catch (e) {
        console.error('addPeriod', e);
        showToast('添加失败', 'error');
    }
}

async function deletePeriod(pid) {
    showCustomConfirm('确定删除该上课时段？', async () => {
        try {
            const result = await api('delete_class_period', { id: pid });
            showToast(result.message);
            loadPeriodTable();
        } catch (e) {
            console.error('deletePeriod', e);
            showToast('删除失败', 'error');
        }
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
    const cssMap = { success: 'hermes-toast--success', error: 'hermes-toast--error', warn: 'hermes-toast--warn', info: 'hermes-toast--info' };
    const cssClass = cssMap[type] || 'hermes-toast--info';
    const existing = document.querySelector('.hermes-toast');
    if (existing) existing.remove();
    const toast = document.createElement('div');
    toast.className = 'hermes-toast ' + cssClass;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(function() { toast.classList.add('hermes-toast--out'); }, 2000);
    setTimeout(function() { if (toast.parentNode) toast.remove(); }, 2500);
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
        else if (tab === 'refund') { refundPage = 1; loadRefundRecords(); }
        else if (tab === 'classroom') { loadClassrooms(); }
    }, 400);
}

// ==================== 弹窗 ====================
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
function openModal(id) {
    // 关闭所有已打开的弹窗
    document.querySelectorAll('.modal-overlay.show').forEach(m => m.classList.remove('show'));
    document.getElementById(id).classList.add('show');
}

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

function applyDatePreset(prefix) {
    const sel = document.getElementById('filter-date-preset-' + prefix);
    const startEl = document.getElementById('filter-created-start-' + prefix);
    const endEl = document.getElementById('filter-created-end-' + prefix);
    if (!sel || !startEl || !endEl) return;
    const val = sel.value;
    if (val === 'custom') {
        startEl.style.display = '';
        endEl.style.display = '';
        return;
    }
    startEl.style.display = 'none';
    endEl.style.display = 'none';
    if (!val) { startEl.value = ''; endEl.value = ''; }
    const today = new Date();
    const fmt = d => d.toISOString().slice(0, 10);
    let start, end;
    switch (val) {
        case 'today': start = end = today; break;
        case 'yesterday': start = end = new Date(today - 86400000); break;
        case '7days': start = new Date(today - 6 * 86400000); end = today; break;
        case '30days': start = new Date(today - 29 * 86400000); end = today; break;
        case 'thisMonth': start = new Date(today.getFullYear(), today.getMonth(), 1); end = today; break;
        case 'lastMonth': start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                         end = new Date(today.getFullYear(), today.getMonth(), 0); break;
        default: startEl.value = ''; endEl.value = ''; return;
    }
    startEl.value = fmt(start);
    if (end) endEl.value = fmt(end);
    debounceMyFilters();
}

function onCustomDateChange(prefix) {
    const sel = document.getElementById('filter-date-preset-' + prefix);
    if (sel && sel.value === 'custom') {
        debounceMyFilters();
    }
}

async function loadMyResources() {
    const keyword = document.getElementById('search-my')?.value || '';
    const name = document.getElementById('filter-name-my')?.value || '';
    const phone = document.getElementById('filter-phone-my')?.value || '';
    const source = document.getElementById('filter-source-my')?.value || '';
    const assignedTo = document.getElementById('filter-assigned-to-my')?.value || '';
    const assignedDept = document.getElementById('filter-assigned-dept-my')?.value || '';
    const createdStart = document.getElementById('filter-created-start-my')?.value || '';
    const createdEnd = document.getElementById('filter-created-end-my')?.value || '';
    const followStatus = document.getElementById('filter-follow-status-my')?.value || '';

    const params = new URLSearchParams({ page: myPage, page_size: myPageSize, pool_type: '我的资源', keyword });
    if (name) params.set('name', name);
    if (phone) params.set('phone', phone);
    if (source) params.set('source', source);
    if (assignedTo) params.set('assigned_to', assignedTo);
    if (assignedDept) params.set('assigned_dept', assignedDept);
    if (createdStart) params.set('created_start', createdStart);
    if (createdEnd) params.set('created_end', createdEnd);
    if (followStatus) params.set('follow_status', followStatus);

    try {
        const res = await fetch(API_BASE + 'get_resources&' + params);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        renderMyTable(data.data || []);
        renderMyPagination(data.total || 0, myPage, myPageSize);
        updateMySelectionUI();
        updateFilterCountBadge();
    } catch (e) {
        console.error('loadMyResources error:', e);
        showToast('加载资源失败，请检查网络后重试', 'error');
        const tbody = document.querySelector('#table-my-resources tbody');
        if (tbody) {
            tbody.innerHTML = renderEmptyState('加载失败', '请检查网络连接后重试');
        }
        document.getElementById('pagination-my').innerHTML = '';
    }
}

function renderMyTable(rows) {
    const tbody = document.querySelector('#table-my-resources tbody');
    if (!tbody) return;

    if (!rows || rows.length === 0) {
        tbody.innerHTML = renderEmptyState('暂无资源数据', '点击「新增资源」添加第一条数据，或调整筛选条件');
        if (document.getElementById('select-all-my')) document.getElementById('select-all-my').checked = false;
        document.getElementById('pagination-my').innerHTML = '';
        return;
    }

    tbody.innerHTML = rows.map(r => renderMyResourceRow(r)).join('');
    if (document.getElementById('select-all-my')) document.getElementById('select-all-my').checked = false;
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

// ---------- 资源弹窗双列布局改造 ----------
// 将 #edit-rid 移出 .modal-body，避免干扰 CSS Grid nth-child 索引
function ensureResourceModalLayout() {
    var hidden = document.getElementById('edit-rid');
    var body = document.querySelector('#modal-resource .modal-body');
    if (!hidden || !body) return;
    // 已在 .modal-body 外部则无需处理
    if (hidden.parentElement !== body) return;
    var modal = body.parentElement; // .modal
    if (modal) modal.insertBefore(hidden, body);
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
    ensureResourceModalLayout();
    openModal('modal-resource');
}

async function editResource(rid) {
    try {
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
        ensureResourceModalLayout();
        openModal('modal-resource');
    } catch(e) {
        console.error('editResource error:', e);
        showToast('编辑失败：' + (e.message || String(e)), 'error');
    }
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

// ---------- 行操作下拉菜单 ----------
function openRowActionMenu(rid, name, phone, btnEl) {
    closeRowActionMenu();
    // 注入样式（仅一次）
    if (!document.getElementById('row-action-dropdown-styles')) {
        var style = document.createElement('style');
        style.id = 'row-action-dropdown-styles';
        style.textContent = '.row-action-dropdown{background:#fff;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,0.12),0 2px 8px rgba(0,0,0,0.06);z-index:10000;overflow:hidden;min-width:120px;animation:fadeInDown 0.15s ease;}.row-action-dropdown-item{padding:10px 16px;font-size:13px;cursor:pointer;color:#333;white-space:nowrap;border-bottom:1px solid #f0f0f0;}.row-action-dropdown-item:last-child{border-bottom:none;}.row-action-dropdown-item:hover{background:#f5f0ff;color:#7C3AED;}.row-action-dropdown-item-danger{color:#e74c3c;}.row-action-dropdown-item-danger:hover{background:#fff0f0;color:#c0392b;}';
        document.head.appendChild(style);
    }
    var dropdown = document.createElement('div');
    dropdown.className = 'row-action-dropdown';
    dropdown._rid = rid;
    dropdown.innerHTML =
        '<div class="row-action-dropdown-item" data-action="appointment">预约试听</div>' +
        '<div class="row-action-dropdown-item" data-action="communication">沟通记录</div>' +
        '<div class="row-action-dropdown-item row-action-dropdown-item-danger" data-action="delete">删除</div>';
    dropdown.addEventListener('click', function(e) {
        var item = e.target.closest('.row-action-dropdown-item');
        if (!item) return;
        var action = item.dataset.action;
        dropdown.remove();
        if (action === 'appointment') {
            openAppointmentForResource(rid, name, phone);
        } else if (action === 'communication') {
            openCommunication(rid, name);
        } else if (action === 'delete') {
            deleteResource(rid);
        }
    });
    document.body.appendChild(dropdown);
    var rect = btnEl.getBoundingClientRect();
    dropdown.style.position = 'fixed';
    dropdown.style.top = (rect.bottom + 4) + 'px';
    dropdown.style.left = rect.left + 'px';
    var closeOnOutside = function(e) {
        if (!dropdown.contains(e.target) && e.target !== btnEl) {
            dropdown.remove();
            document.removeEventListener('click', closeOnOutside);
        }
    };
    setTimeout(function() { document.addEventListener('click', closeOnOutside); }, 0);
}

function closeRowActionMenu() {
    var existing = document.querySelector('.row-action-dropdown');
    if (existing) existing.remove();
}

// ==================== 我的资源：增强交互 ====================

// ---------- 渲染单行 ----------
function renderMyResourceRow(r) {
    return `<tr class="my-resource-row" data-rid="${r.id}">
        <td><input type="checkbox" class="cb-my" value="${r.id}"></td>
        <td class="editable-name" data-rid="${r.id}" data-value="${esc(r.name)}" title="点击编辑姓名">
            <span class="cell-text">${esc(r.name)}</span>
        </td>
        <td class="editable-phone" data-rid="${r.id}" data-value="${esc(r.phone)}" title="点击编辑电话">
            <a href="tel:${esc(r.phone)}" class="phone-link" onclick="event.stopPropagation()" style="color:#7C3AED;text-decoration:none;">${esc(r.phone)}</a>
        </td>
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
                <button class="btn-link btn-action-edit" data-rid="${r.id}">编辑</button>
                <button class="btn-link btn-action-enroll" data-rid="${r.id}">报名</button>
                <button class="btn-link btn-action-more" data-rid="${r.id}" data-name="${esc(r.name)}" data-phone="${esc(r.phone)}">更多 ▾</button>
            </div>
        </td>
    </tr>`;
}

// ---------- 空状态 ----------
function renderEmptyState(title, desc) {
    return `<tr><td colspan="14" style="text-align:center;padding:60px 20px;">
        <div style="margin-bottom:20px;">
            <svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;">
                <rect x="16" y="22" width="48" height="40" rx="4" stroke="#C4B5FD" stroke-width="2" fill="#F5F3FF"/>
                <rect x="24" y="32" width="32" height="3" rx="1.5" fill="#DDD6FE"/>
                <rect x="24" y="40" width="22" height="3" rx="1.5" fill="#EDE9FE"/>
                <rect x="24" y="48" width="18" height="3" rx="1.5" fill="#F3F0FF"/>
                <circle cx="60" cy="18" r="11" stroke="#A78BFA" stroke-width="2" fill="#F5F3FF"/>
                <circle cx="60" cy="18" r="5.5" stroke="#C4B5FD" stroke-width="1.5"/>
                <line x1="67.5" y1="25.5" x2="74" y2="32" stroke="#A78BFA" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </div>
        <div style="font-size:16px;color:#6D28D9;margin-bottom:8px;font-weight:600;">${title}</div>
        <div style="font-size:13px;color:#A78BFA;">${desc}</div>
    </td></tr>`;
}

// ---------- 我的资源分页 ----------
function renderMyPagination(total, page, pageSize) {
    const totalPages = Math.max(1, Math.ceil(total / pageSize));
    const container = document.getElementById('pagination-my');
    if (!container) return;

    let html = '<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;width:100%;">';

    html += '<div style="display:flex;align-items:center;gap:12px;">';
    html += '<span style="font-size:13px;color:#6B7280;">共 <strong>' + total + '</strong> 条，第 <strong>' + page + '</strong>/<strong>' + totalPages + '</strong> 页</span>';
    html += '<select onchange="onMyPageSizeChange(this.value)" style="padding:4px 8px;border:1px solid #ddd;border-radius:4px;font-size:13px;cursor:pointer;">';
    [15, 30, 50].forEach(function(s) {
        html += '<option value="' + s + '"' + (s === pageSize ? ' selected' : '') + '>每页 ' + s + ' 条</option>';
    });
    html += '</select></div>';

    if (totalPages > 1) {
        html += '<div style="display:flex;align-items:center;gap:4px;">';
        html += '<button' + (page === 1 ? ' disabled' : '') + ' data-page="' + (page - 1) + '">上一页</button>';
        var maxShow = 5;
        var start = Math.max(1, page - Math.floor(maxShow / 2));
        var end = Math.min(totalPages, start + maxShow - 1);
        if (end - start + 1 < maxShow) start = Math.max(1, end - maxShow + 1);
        if (start > 1) { html += '<button data-page="1">1</button>'; if (start > 2) html += '<span style="padding:0 4px;">...</span>'; }
        for (var i = start; i <= end; i++) {
            html += '<button' + (i === page ? ' class="active"' : '') + ' data-page="' + i + '">' + i + '</button>';
        }
        if (end < totalPages) { if (end < totalPages - 1) html += '<span style="padding:0 4px;">...</span>'; html += '<button data-page="' + totalPages + '">' + totalPages + '</button>'; }
        html += '<button' + (page === totalPages ? ' disabled' : '') + ' data-page="' + (page + 1) + '">下一页</button>';
        html += '</div>';
    }

    html += '</div>';
    container.innerHTML = html;
    container.querySelectorAll('button:not([disabled])').forEach(function(btn) {
        btn.addEventListener('click', function() {
            myPage = parseInt(btn.dataset.page);
            loadMyResources();
        });
    });
}

function onMyPageSizeChange(size) {
    myPageSize = parseInt(size);
    myPage = 1;
    loadMyResources();
}

// ---------- 行内快速编辑 ----------
function startInlineEditMy(cell, rid, field) {
    if (cell.querySelector('input')) return;

    var currentValue = cell.dataset.value || '';
    var displayEl = cell.querySelector('.cell-text') || cell.querySelector('a');

    var input = document.createElement('input');
    input.type = 'text';
    input.className = 'inline-edit-input';
    input.value = currentValue;
    input.style.cssText = 'width:100%;padding:3px 6px;border:2px solid #7C3AED;border-radius:4px;font-size:13px;outline:none;box-sizing:border-box;';

    if (displayEl) displayEl.style.display = 'none';
    cell.appendChild(input);
    input.focus();
    input.select();

    var saving = false;
    var doSave = async function() {
        if (saving) return;
        var newValue = input.value.trim();
        if (newValue === currentValue) {
            input.remove();
            if (displayEl) displayEl.style.display = '';
            return;
        }
        if (!newValue && field === 'name') {
            showToast('姓名不能为空', 'error');
            input.focus();
            return;
        }
        if (!newValue && field === 'phone') {
            showToast('电话不能为空', 'error');
            input.focus();
            return;
        }
        saving = true;
        input.disabled = true;
        try {
            var payload = { id: rid };
            payload[field] = newValue;
            var result = await api('update_resource', payload);
            if (result.error) {
                showToast(result.error, 'error');
                input.disabled = false;
                input.focus();
                saving = false;
                return;
            }
            showToast('已更新', 'success');
            cell.dataset.value = newValue;
            if (field === 'phone' && displayEl && displayEl.tagName === 'A') {
                displayEl.href = 'tel:' + newValue;
                displayEl.textContent = newValue;
            } else if (displayEl) {
                displayEl.textContent = newValue;
            }
            input.remove();
            if (displayEl) displayEl.style.display = '';
            loadStats();
        } catch (e) {
            showToast('网络异常，请重试', 'error');
            input.disabled = false;
            input.focus();
            saving = false;
        }
    };

    var doCancel = function() {
        input.remove();
        if (displayEl) displayEl.style.display = '';
    };

    input.addEventListener('blur', doSave);
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { input.blur(); }
        if (e.key === 'Escape') { doCancel(); }
    });
}

// ---------- 选中状态 UI 同步 ----------
function updateMySelectionUI() {
    var allCbs = document.querySelectorAll('.cb-my');
    var checkedCbs = document.querySelectorAll('.cb-my:checked');
    var selectAll = document.getElementById('select-all-my');
    var n = checkedCbs.length;

    if (selectAll) {
        selectAll.checked = allCbs.length > 0 && n === allCbs.length;
    }

    document.querySelectorAll('#table-my-resources tbody tr').forEach(function(row) {
        var cb = row.querySelector('.cb-my');
        if (cb && cb.checked) {
            row.classList.add('row-selected');
        } else {
            row.classList.remove('row-selected');
        }
    });

    var batchBtnSelectors = [
        '#panel-my-resources .action-btn[onclick*="batchAssign"]',
        '#panel-my-resources .action-btn[onclick*="batchMoveToSea"]',
        '#panel-my-resources .action-btn[onclick*="openBatchCommunication"]'
    ];
    batchBtnSelectors.forEach(function(sel) {
        var btn = document.querySelector(sel);
        if (!btn) return;
        if (n === 0) {
            btn.classList.add('action-btn-disabled');
            btn.style.opacity = '0.45';
            btn.style.pointerEvents = 'none';
        } else {
            btn.classList.remove('action-btn-disabled');
            btn.style.opacity = '';
            btn.style.pointerEvents = '';
        }
    });

    var countEl = document.getElementById('my-selected-count');
    if (!countEl) {
        countEl = document.createElement('span');
        countEl.id = 'my-selected-count';
        countEl.style.cssText = 'margin-left:12px;font-size:13px;color:#7C3AED;font-weight:500;';
        var actionGroup = document.querySelector('#panel-my-resources .action-button-group');
        if (actionGroup) actionGroup.appendChild(countEl);
    }
    countEl.textContent = n > 0 ? '已选 ' + n + ' 项' : '';
    countEl.style.display = n > 0 ? '' : 'none';
}

// ---------- 筛选条件计数徽章 ----------
function updateFilterCountBadge() {
    var badge = document.getElementById('filter-count-badge-my');
    if (!badge) return;
    var count = 0;
    var ids = ['filter-name-my', 'filter-phone-my', 'filter-source-my',
               'filter-assigned-to-my', 'filter-assigned-dept-my',
               'filter-date-preset-my', 'filter-follow-status-my'];
    ids.forEach(function(id) {
        var el = document.getElementById(id);
        if (el && el.value) count++;
    });
    var startEl = document.getElementById('filter-created-start-my');
    var endEl = document.getElementById('filter-created-end-my');
    if (startEl && startEl.style.display !== 'none' && startEl.value) count++;
    if (endEl && endEl.style.display !== 'none' && endEl.value) count++;

    if (count > 0) {
        badge.style.display = 'inline';
        badge.textContent = count + '项筛选';
    } else {
        badge.style.display = 'none';
    }
}

// ---------- 防抖筛选 ----------
function debounceMyFilters() {
    clearTimeout(myFilterTimer);
    myFilterTimer = setTimeout(function() {
        myPage = 1;
        loadMyResources();
    }, 300);
}

// ---------- 重置筛选 ----------
function resetMyFilters() {
    document.getElementById('filter-name-my').value = '';
    document.getElementById('filter-phone-my').value = '';
    document.getElementById('filter-source-my').value = '';
    document.getElementById('filter-assigned-to-my').value = '';
    document.getElementById('filter-assigned-dept-my').value = '';
    document.getElementById('filter-date-preset-my').value = '';
    document.getElementById('filter-created-start-my').value = '';
    document.getElementById('filter-created-start-my').style.display = 'none';
    document.getElementById('filter-created-end-my').value = '';
    document.getElementById('filter-created-end-my').style.display = 'none';
    document.getElementById('filter-follow-status-my').value = '';
    document.getElementById('search-my').value = '';
    myPage = 1;
    loadMyResources();
}

// ---------- 面板初始化（事件委托 + 工具栏改造） ----------
function initMyResourcesPanel() {
    var table = document.getElementById('table-my-resources');
    if (!table) return;

    table.addEventListener('click', function(e) {
        // 操作按钮事件委托
        var editBtn = e.target.closest('.btn-action-edit');
        if (editBtn) { var rid = parseInt(editBtn.dataset.rid); if (rid) editResource(rid); return; }
        var enrollBtn = e.target.closest('.btn-action-enroll');
        if (enrollBtn) { var rid = parseInt(enrollBtn.dataset.rid); if (rid) goEnrollFromResource(rid); return; }
        var moreBtn = e.target.closest('.btn-action-more');
        if (moreBtn) { var rid = parseInt(moreBtn.dataset.rid); var name = moreBtn.dataset.name || ''; var phone = moreBtn.dataset.phone || ''; if (rid) openRowActionMenu(rid, name, phone, moreBtn); return; }
        
        var row = e.target.closest('tr.my-resource-row');
        if (row && !e.target.closest('a, button, input, select, .follow-status-tag')) {
            var cb = row.querySelector('.cb-my');
            if (cb) { cb.checked = !cb.checked; updateMySelectionUI(); }
        }
        var nameCell = e.target.closest('td.editable-name');
        if (nameCell && !e.target.closest('input')) {
            var rid = parseInt(nameCell.dataset.rid);
            if (rid) startInlineEditMy(nameCell, rid, 'name');
            return;
        }
        var phoneCell = e.target.closest('td.editable-phone');
        if (phoneCell && !e.target.closest('a, input')) {
            var rid2 = parseInt(phoneCell.dataset.rid);
            if (rid2) startInlineEditMy(phoneCell, rid2, 'phone');
        }
    });

    table.addEventListener('change', function(e) {
        if (e.target.classList.contains('cb-my')) {
            updateMySelectionUI();
        }
    });

    // 筛选下拉框改为防抖
    var filterSelects = ['filter-source-my', 'filter-assigned-to-my',
                         'filter-assigned-dept-my', 'filter-follow-status-my'];
    filterSelects.forEach(function(id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.onchange = null;
        el.addEventListener('change', function() { debounceMyFilters(); });
    });

    ['filter-name-my', 'filter-phone-my'].forEach(function(id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('input', function() { debounceMyFilters(); });
    });

    // 隐藏「搜索」按钮
    var toolbarRight = document.querySelector('#panel-my-resources .toolbar-right');
    if (toolbarRight) {
        var searchBtn = toolbarRight.querySelector('button.btn-primary, button.btn');
        if (searchBtn && (searchBtn.textContent || '').trim() === '搜索') {
            searchBtn.style.display = 'none';
        }
    }

    // 添加「重置筛选」按钮
    if (toolbarRight && !document.getElementById('btn-reset-my-filters')) {
        var resetBtn = document.createElement('button');
        resetBtn.id = 'btn-reset-my-filters';
        resetBtn.className = 'btn btn-sm';
        resetBtn.textContent = '重置';
        resetBtn.title = '重置所有筛选条件';
        resetBtn.style.cssText = 'margin-left:8px;background:#f3f4f6;color:#374151;border:1px solid #d1d5db;';
        resetBtn.addEventListener('click', resetMyFilters);
        toolbarRight.insertBefore(resetBtn, toolbarRight.firstChild);
    }

    // 添加筛选计数徽章
    if (toolbarRight && !document.getElementById('filter-count-badge-my')) {
        var badge = document.createElement('span');
        badge.id = 'filter-count-badge-my';
        badge.className = 'filter-count-badge';
        badge.style.cssText = 'display:none;margin-left:6px;background:#7C3AED;color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;font-weight:500;';
        toolbarRight.appendChild(badge);
    }

    updateFilterCountBadge();
    updateMySelectionUI();
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
    if (tab === 'my') updateMySelectionUI();
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
    showTrialAppointment(rid, name, phone);
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
    renderAptStats(data.stats);
    renderPagination('pagination-apt', data.total, aptPage, 15, (p) => { aptPage = p; loadAppointments(); });
}

function renderAptStats(stats) {
    if (!stats) return;
    document.getElementById('stat-pending').textContent = stats['已预约待试听'] ?? 0;
    document.getElementById('stat-trialed').textContent = stats['已试听'] ?? 0;
    document.getElementById('stat-absent').textContent = stats['缺勤'] ?? 0;
}

function renderAptTable(rows) {
    const tbody = document.querySelector('#table-appointments tbody');
    if (!rows || rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="14" style="text-align:center;color:#999;padding:30px;">暂无预约记录</td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(r => `
        <tr>
            <td>${esc(r.resource_name)}</td>
            <td>${esc(r.phone)}</td>
            <td>${esc(r.course_type)}</td>
            <td>${esc(r.class_name)}</td>
            <td>${esc(r.session_teacher)}</td>
            <td>${esc(r.subject_level1)}</td>
            <td>${esc(r.subject_level2)}</td>
            <td>${r.appointment_time ? r.appointment_time.slice(0,16) : ''}</td>
            <td><span class="status-tag status-${r.status}">${r.status}</span></td>
            <td><span class="status-tag status-converted-${r.resource_converted === '已转化' ? 'yes' : 'no'}">${esc(r.resource_converted)}</span></td>
            <td>${esc(r.resource_channel)}</td>
            <td>${esc(r.resource_assigned_to)}</td>
            <td>${esc(r.notes) || '<span class="text-muted">—</span>'}</td>
            <td>
                <div class="action-btns">
                    <button class="btn-link-danger" onclick="cancelTrial(${r.id},${r.class_id},${r.schedule_id},'${(r.appointment_time||'').slice(0,10)}')">取消试听</button>
                </div>
            </td>
        </tr>
    `).join('');
}

async function cancelTrial(aptId, classId, scheduleId, trialDate) {
    showCustomConfirm('确定取消该试听预约吗？考勤记录也将一并移除。', async function() {
        try {
            const res = await api('cancel_trial', {id: aptId, class_id: classId, schedule_id: scheduleId, trial_date: trialDate}, 'POST');
            if (res.error) { showToast(res.error, 'error'); return; }
            showToast('已取消试听', 'success');
            loadAppointments();
            loadStats();
        } catch(e) { showToast('操作失败: ' + (e.message || String(e)), 'error'); }
    });
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
    const subject1 = document.getElementById('filter-subject1')?.value || '';
    const subject2 = document.getElementById('filter-subject2')?.value || '';
    const smallPackage = document.getElementById('filter-small-package')?.value || '';
    const toddler = document.getElementById('filter-toddler')?.value || '';
    const campusIds = getFilterCampusIds();
    if (subject1) params.set('subject_level1', subject1);
    if (subject2) params.set('subject_level2', subject2);
    if (smallPackage) params.set('small_package', smallPackage);
    if (toddler) params.set('toddler', toddler);
    if (campusIds) params.set('campus_ids', campusIds);
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

// 校区权限辅助函数（树形结构）
let campusCheckboxData = []; // [{id, name, type, parent_id}]

async function loadCampusTree(containerId) {
    containerId = containerId || 'course-campus-tree';
    var isSingle = (containerId === 'class-campus-tree');
    const container = document.getElementById(containerId);
    if (!container) return;
    container.innerHTML = '<span style="color:#999;font-size:13px;">加载中...</span>';
    try {
        const res = await fetch(API_BASE + 'list_organizations');
        const data = await res.json();
        const tree = (data && data.data && data.data.tree) ? data.data.tree : [];
        campusCheckboxData = (data && data.data && data.data.flat) ? data.data.flat : [];
        // 递归剪枝：只保留含校区节点的子树
        function pruneTree(nodes) {
            return nodes.filter(function(n) {
                if (n.type === '校区') return true;
                if (n.children && n.children.length > 0) {
                    n.children = pruneTree(n.children);
                    return n.children.length > 0;
                }
                return false;
            });
        }
        const filteredTree = pruneTree(tree);
        if (filteredTree.length === 0) {
            container.innerHTML = '<span style="color:#999;font-size:13px;">暂无校区数据，请先在组织管理中创建校区</span>';
            return;
        }
        container.innerHTML = filteredTree.map(node => renderCampusTreeNode(node, 0, isSingle)).join('');
        if (!isSingle) updateCampusToggleLabel();
    } catch (e) {
        container.innerHTML = '<span style="color:#e6a23c;font-size:13px;">加载校区失败</span>';
    }
}

function renderCampusTreeNode(node, level, isSingle) {
    const hasChildren = node.children && node.children.length > 0;
    const expanded = level === 0;
    var isCampus = (node.type === '校区');
    let html = '<div class="campus-tree-node" data-id="' + node.id + '" data-type="' + (node.type || '') + '" data-has-children="' + hasChildren + '" data-expanded="' + expanded + '">';
    html += '<div class="campus-tree-row" style="padding-left:' + (level * 20 + 12) + 'px">';
    if (hasChildren) {
        html += '<span class="campus-tree-arrow" onclick="toggleCampusTreeExpand(this)">' + (expanded ? '▾' : '▸') + '</span>';
    } else {
        html += '<span class="campus-tree-arrow" style="visibility:hidden;">▸</span>';
    }
    if (isSingle && isCampus) {
        html += '<input type="radio" name="class-campus-radio" class="campus-tree-check" value="' + node.name + '" onclick="onClassCampusRadio(this)">';
    } else if (!isSingle) {
        html += '<input type="checkbox" class="campus-tree-check" onclick="toggleCampusTreeNode(this)">';
    }
    html += '<span class="campus-tree-label">' + esc(node.name) + '</span>';
    html += '</div>';
    if (hasChildren) {
        html += '<div class="campus-tree-children" style="display:' + (expanded ? 'block' : 'none') + '">';
        html += node.children.map(function(child) { return renderCampusTreeNode(child, level + 1, isSingle); }).join('');
        html += '</div>';
    }
    html += '</div>';
    return html;
}

function onClassCampusRadio(el) {
    // Single-select radio — no special handling needed
}

function toggleCampusTreeExpand(el) {
    const node = el.closest('.campus-tree-node');
    if (!node) return;
    const children = node.querySelector('.campus-tree-children');
    if (!children) return;
    const expanded = node.dataset.expanded === 'true';
    node.dataset.expanded = expanded ? 'false' : 'true';
    el.textContent = expanded ? '▸' : '▾';
    children.style.display = expanded ? 'none' : '';
}

function toggleCampusTreeNode(el) {
    const node = el.closest('.campus-tree-node');
    if (!node) return;
    const checked = el.checked;
    // 传播到所有后代 checkbox
    node.querySelectorAll('.campus-tree-check').forEach(function(c) { c.checked = checked; c.indeterminate = false; });
    // 向上更新父节点三态
    const parent = node.parentElement && node.parentElement.closest('.campus-tree-node');
    if (parent) updateTreeNodeState(parent);
    updateCampusToggleLabel();
}

function updateTreeNodeState(node) {
    const check = node.querySelector('.campus-tree-check');
    if (!check) return;
    // 统计该节点下所有校区类型后代的勾选情况
    const leafChecks = node.querySelectorAll('.campus-tree-node[data-type="校区"] .campus-tree-check');
    if (leafChecks.length === 0) return;
    const checkedCount = Array.from(leafChecks).filter(function(c) { return c.checked; }).length;
    if (checkedCount === 0) {
        check.checked = false;
        check.indeterminate = false;
    } else if (checkedCount === leafChecks.length) {
        check.checked = true;
        check.indeterminate = false;
    } else {
        check.checked = false;
        check.indeterminate = true;
    }
    // 继续向上传播
    const parent = node.parentElement && node.parentElement.closest('.campus-tree-node');
    if (parent) updateTreeNodeState(parent);
}

function toggleAllCampuses() {
    const checks = document.querySelectorAll('#course-campus-tree .campus-tree-node[data-type="校区"] .campus-tree-check');
    if (checks.length === 0) return;
    const allChecked = Array.from(checks).every(function(c) { return c.checked; });
    checks.forEach(function(c) { c.checked = !allChecked; c.indeterminate = false; });
    // 更新所有父节点三态
    document.querySelectorAll('#course-campus-tree .campus-tree-node[data-has-children="true"]').forEach(function(n) {
        updateTreeNodeState(n);
    });
    updateCampusToggleLabel();
}

function updateCampusToggleLabel() {
    const btn = document.getElementById('campus-toggle-all');
    const checks = document.querySelectorAll('#course-campus-tree .campus-tree-node[data-type="校区"] .campus-tree-check');
    if (!btn || checks.length === 0) return;
    const allChecked = Array.from(checks).every(function(c) { return c.checked; });
    btn.textContent = allChecked ? '清空' : '全选';
}

function getSelectedCampuses() {
    const checks = document.querySelectorAll('#course-campus-tree .campus-tree-node[data-type="校区"] .campus-tree-check:checked');
    return Array.from(checks).map(function(c) { return c.closest('.campus-tree-node').dataset.id; }).join(',');
}

function clearFormErrors() {
    document.querySelectorAll('.form-error').forEach(e => e.textContent = '');
    document.getElementById('course-name').classList.remove('input-error');
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
    const container = document.getElementById('table-courses');
    const emptyEl = container.querySelector('.course-cards-empty');
    // 清空除 empty 占位元素外的所有卡片
    container.querySelectorAll('.course-card').forEach(el => el.remove());
    if (!rows.length) {
        if (emptyEl) emptyEl.style.display = 'block';
        return;
    }
    if (emptyEl) emptyEl.style.display = 'none';
    rows.forEach(r => {
        const campusText = getCampusDisplayText(r.campus_permission);
        const card = document.createElement('div');
        card.className = 'course-card';
        card.innerHTML = `
            <div class="course-card-body">
                <div class="course-card-main">
                    <h4 class="course-card-name">${esc(r.name)}</h4>
                    <div class="course-card-subjects">
                        <span class="course-tag course-tag-level1">${esc(r.subject_level1) || '-'}</span>
                        ${r.subject_level2 ? '<span class="course-tag course-tag-level2">' + esc(r.subject_level2) + '</span>' : ''}
                    </div>
                </div>
                <div class="course-card-meta">
                    <span class="course-meta-item"><span class="course-meta-label">校区</span>${campusText || '-'}</span>
                    <span class="course-meta-item"><span class="course-meta-label">小课包</span>${esc(r.small_package) || '-'}</span>
                    <span class="course-meta-item"><span class="course-meta-label">低幼龄</span>${esc(r.toddler) || '-'}</span>
                </div>
                <div class="course-card-actions">
                    <button class="btn-card-action btn-card-price" onclick="showPriceModal(${r.id}, '${esc(r.name).replace(/'/g, "\\'")}', '${esc(r.small_package || '').replace(/'/g, "\\'")}')" title="设置价格">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        <span>设置价格</span>
                    </button>
                    <button class="btn-card-action btn-card-edit" onclick="editCourse(${r.id})" title="编辑">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        <span>编辑</span>
                    </button>
                    <button class="btn-card-action btn-card-delete" onclick="deleteCourse(${r.id})" title="删除">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        <span>删除</span>
                    </button>
                </div>
            </div>
        `;
        container.appendChild(card);
    });
}

async function showCourseModal(id = null) {
    document.getElementById('modal-course-title').textContent = id ? '编辑课程' : '新增课程';
    document.getElementById('edit-cid').value = id || '';
    clearFormErrors();

    await loadSubjectLevel1Options();
    await loadCampusTree();

    if (id) {
        const res = await fetch(API_BASE + 'list_courses&page=1&page_size=200');
        const data = await res.json();
        const course = (data.data || []).find(c => c.id == id);
        if (course) {
            document.getElementById('course-name').value = course.name;
            document.getElementById('course-subject-level1').value = course.subject_level1 || '';
            if (course.subject_level1) {
                await loadSubjectLevel2Options(course.subject_level1);
            }
            document.getElementById('course-subject-level2').value = course.subject_level2 || '';
            document.getElementById('course-small-package').value = course.small_package || '';
            document.getElementById('course-toddler').value = course.toddler || '';
            // 回填校区树
            if (course.campus_permission) {
                const selectedIds = course.campus_permission.split(',').map(s => s.trim());
                document.querySelectorAll('#course-campus-tree .campus-tree-node[data-type="校区"]').forEach(function(node) {
                    const check = node.querySelector('.campus-tree-check');
                    if (check) check.checked = selectedIds.includes(node.dataset.id);
                });
                // 更新所有父节点三态
                document.querySelectorAll('#course-campus-tree .campus-tree-node[data-has-children="true"]').forEach(function(n) {
                    updateTreeNodeState(n);
                });
                updateCampusToggleLabel();
            }
        }
    } else {
        document.getElementById('course-name').value = '';
        document.getElementById('course-subject-level1').value = '';
        document.getElementById('course-subject-level2').value = '';
        document.getElementById('course-small-package').value = '';
        document.getElementById('course-toddler').value = '';
        document.querySelectorAll('#course-campus-tree .campus-tree-check').forEach(function(c) { c.checked = false; c.indeterminate = false; });
        updateCampusToggleLabel();
    }
    document.getElementById('course-subject-level1').onchange = async function() {
        const parentName = this.value;
        await loadSubjectLevel2Options(parentName);
    };
    openModal('modal-course');
}

async function loadSubjectLevel1Options() {
    const select = document.getElementById('course-subject-level1');
    select.innerHTML = '<option value="">请选择一级学科</option>';
    try {
        const result = await api('list_subjects', null, 'GET');
        if (result && result.tree) {
            result.tree.forEach(parent => {
                const opt = document.createElement('option');
                opt.value = parent.name;
                opt.textContent = parent.name;
                select.appendChild(opt);
            });
        }
    } catch (e) {
        // 静默处理
    }
}

async function loadSubjectLevel2Options(parentName) {
    const select = document.getElementById('course-subject-level2');
    select.innerHTML = '<option value="">请选择二级学科</option>';
    if (!parentName) return;
    try {
        const result = await api('list_subjects', null, 'GET');
        if (result && result.tree) {
            const parent = result.tree.find(p => p.name === parentName);
            if (parent && parent.children && parent.children.length > 0) {
                parent.children.forEach(child => {
                    const opt = document.createElement('option');
                    opt.value = child.name;
                    opt.textContent = child.name;
                    select.appendChild(opt);
                });
            }
        }
    } catch (e) {
        // 静默处理
    }
}

async function editCourse(id) {
    showCourseModal(id);
}

async function saveCourse() {
    const btn = document.getElementById('btn-save-course');
    const originalText = btn.textContent;
    btn.textContent = '保存中...';
    btn.disabled = true;
    clearFormErrors();

    const cid = document.getElementById('edit-cid').value;
    const name = document.getElementById('course-name').value.trim();
    const subject_level1 = document.getElementById('course-subject-level1').value.trim();
    const subject_level2 = document.getElementById('course-subject-level2').value.trim();
    const small_package = document.getElementById('course-small-package').value.trim();
    const toddler = document.getElementById('course-toddler').value.trim();
    const campus_permission = getSelectedCampuses();

    let hasError = false;
    if (!name) {
        document.getElementById('err-course-name').textContent = '请输入课程名称';
        document.getElementById('course-name').classList.add('input-error');
        hasError = true;
    }
    if (!campus_permission) {
        document.getElementById('err-campus').textContent = '请至少选择一个校区';
        hasError = true;
    }
    if (hasError) {
        btn.textContent = originalText;
        btn.disabled = false;
        return;
    }

    const action = cid ? 'update_course' : 'add_course';
    const payload = cid ? { id: parseInt(cid), name, subject_level1, subject_level2, small_package, toddler, campus_permission }
                        : { name, subject_level1, subject_level2, small_package, toddler, campus_permission };
    try {
        const r = await api(action, payload, 'POST');
        if (r && r.error) {
            showToast(r.error, 'error');
            if (r.error.includes('已存在')) {
                document.getElementById('err-course-name').textContent = '课程名称已存在';
                document.getElementById('course-name').classList.add('input-error');
            }
            btn.textContent = originalText;
            btn.disabled = false;
            return;
        }
        closeModal('modal-course');
        showToast(cid ? '课程更新成功' : '课程添加成功');
        loadCourses();
        loadStats();
    } catch (e) {
        showToast('保存失败：' + e.message, 'error');
    } finally {
        btn.textContent = originalText;
        btn.disabled = false;
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

// ==================== 课程筛选 ====================
function onFilterChange() {
    coursePage = 1;
    loadCourses();
}

async function onFilterSubject1Change() {
    const val = document.getElementById('filter-subject1').value;
    const sel2 = document.getElementById('filter-subject2');
    sel2.innerHTML = '<option value="">全部</option>';
    if (val && filterSubjectsFlat) {
        filterSubjectsFlat.filter(s => String(s.parent_id) !== '0' && s.parent_name === val).forEach(s => {
            sel2.innerHTML += '<option value="' + esc(s.name) + '">' + esc(s.name) + '</option>';
        });
    }
    onFilterChange();
}

let filterSubjectsFlat = [];
let filterCampusFlat = [];

async function loadFilterSubjects() {
    try {
        const res = await fetch(API_BASE + 'list_subjects');
        const data = await res.json();
        const flat = (data && data.flat) ? data.flat : [];
        // 为每个二级学科附加 parent_name
        const idMap = {};
        flat.forEach(s => { idMap[s.id] = s.name; });
        flat.forEach(s => {
            s.parent_name = s.parent_id ? (idMap[s.parent_id] || '') : '';
        });
        filterSubjectsFlat = flat;
        const sel1 = document.getElementById('filter-subject1');
        if (sel1) {
            flat.filter(s => !s.parent_id || s.parent_id == 0).forEach(s => {
                sel1.innerHTML += '<option value="' + esc(s.name) + '">' + esc(s.name) + '</option>';
            });
        }
    } catch (e) { /* 静默 */ }
}

async function loadFilterCampusTree() {
    const container = document.getElementById('filter-campus-tree');
    if (!container) return;
    try {
        const res = await fetch(API_BASE + 'list_organizations');
        const data = await res.json();
        const tree = (data && data.data && data.data.tree) ? data.data.tree : [];
        filterCampusFlat = (data && data.data && data.data.flat) ? data.data.flat.filter(o => o.type === '校区') : [];
        // 剪枝
        function prune(nodes) {
            return nodes.filter(function(n) {
                if (n.type === '校区') return true;
                if (n.children && n.children.length > 0) {
                    n.children = prune(n.children);
                    return n.children.length > 0;
                }
                return false;
            });
        }
        const filtered = prune(tree);
        container.innerHTML = filtered.map(n => renderCampusTreeNode(n, 0)).join('');
        updateFilterCampusToggleLabel();
    } catch (e) { /* 静默 */ }
}

function getFilterCampusIds() {
    const checks = document.querySelectorAll('#filter-campus-tree .campus-tree-node[data-type="校区"] .campus-tree-check:checked');
    return Array.from(checks).map(c => c.closest('.campus-tree-node').dataset.id).join(',');
}

function updateFilterCampusTriggerLabel() {
    const btn = document.getElementById('filter-campus-trigger');
    const ids = getFilterCampusIds();
    if (!ids) { btn.textContent = '全部校区 ▾'; return; }
    const count = ids.split(',').length;
    btn.textContent = '已选' + count + '个校区 ▾';
}

function updateFilterCampusToggleLabel() {
    const btn = document.getElementById('filter-campus-toggle-all');
    const checks = document.querySelectorAll('#filter-campus-tree .campus-tree-node[data-type="校区"] .campus-tree-check');
    if (!btn || checks.length === 0) return;
    const allChecked = Array.from(checks).every(c => c.checked);
    btn.textContent = allChecked ? '清空' : '全选';
}

function toggleFilterCampusPanel() {
    const panel = document.getElementById('filter-campus-panel');
    if (!panel) return;
    const show = panel.style.display !== 'block';
    panel.style.display = show ? 'block' : 'none';
    if (show && !document.getElementById('filter-campus-tree').innerHTML.trim()) {
        loadFilterCampusTree();
    }
}

function filterCampusToggleAll() {
    const checks = document.querySelectorAll('#filter-campus-tree .campus-tree-node[data-type="校区"] .campus-tree-check');
    if (checks.length === 0) return;
    const allChecked = Array.from(checks).every(c => c.checked);
    checks.forEach(c => { c.checked = !allChecked; c.indeterminate = false; });
    document.querySelectorAll('#filter-campus-tree .campus-tree-node[data-has-children="true"]').forEach(n => updateTreeNodeState(n));
    updateFilterCampusToggleLabel();
    updateFilterCampusTriggerLabel();
    onFilterChange();
}

function clearFilterCampus() {
    document.querySelectorAll('#filter-campus-tree .campus-tree-check').forEach(c => { c.checked = false; c.indeterminate = false; });
    updateFilterCampusToggleLabel();
    updateFilterCampusTriggerLabel();
    // 不在这里触发 onFilterChange()，由关闭面板或用户主动操作触发
}

async function resetCourseFilters() {
    document.getElementById('search-course').value = '';
    document.getElementById('filter-subject1').value = '';
    document.getElementById('filter-subject2').innerHTML = '<option value="">全部</option>';
    document.getElementById('filter-small-package').value = '';
    document.getElementById('filter-toddler').value = '';
    clearFilterCampus();
    document.getElementById('filter-campus-trigger').textContent = '全部校区 ▾';
    onFilterChange();
}

// 点击面板外部关闭
document.addEventListener('click', function(e) {
    const panel = document.getElementById('filter-campus-panel');
    const trigger = document.getElementById('filter-campus-trigger');
    if (!panel || !trigger) return;
    if (!trigger.contains(e.target) && !panel.contains(e.target)) {
        panel.style.display = 'none';
    }
});

// 监听筛选校区树的 checkbox 变化（委托）
document.addEventListener('change', function(e) {
    if (e.target && e.target.classList.contains('campus-tree-check') && e.target.closest('#filter-campus-tree')) {
        updateFilterCampusToggleLabel();
        updateFilterCampusTriggerLabel();
    }
});

// 覆盖 toggleCampusTreeNode：如果是筛选面板的树，需要额外更新 trigger label 和触发筛选
var _origToggleCampusTreeNode = toggleCampusTreeNode;
toggleCampusTreeNode = function(el) {
    _origToggleCampusTreeNode(el);
    if (el.closest('#filter-campus-tree')) {
        updateFilterCampusToggleLabel();
        updateFilterCampusTriggerLabel();
        onFilterChange();
    }
};

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

async function loadClasses(page, prefix) {
    prefix = prefix || '';
    if (page) classPage = page;
    const searchId = prefix ? (prefix + 'search-class') : 'search-class';
    const keyword = document.getElementById(searchId) ? document.getElementById(searchId).value : '';
    const params = new URLSearchParams({ page: classPage, page_size: 15 });
    if (keyword) params.set('keyword', keyword);
    const res = await fetch(API_BASE + 'list_classes&' + params);
    const data = await res.json();
    renderClassTable(data.data, prefix);
    renderPagination(prefix + 'pagination-' + (prefix ? 'att-' : '') + 'class', data.total, classPage, 15, (p) => { classPage = p; loadClasses(null, prefix); });
    var statEl = document.getElementById((prefix ? '' : 'stat-classes-inline'));
    if (statEl) statEl.textContent = data.total || 0;
}

function renderClassTable(rows, prefix) {
    prefix = prefix || '';
    var tableId = prefix ? (prefix + 'table-classes') : 'table-classes';
    const tbody = document.querySelector('#' + tableId + ' tbody');
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
    document.getElementById('class-type-group').style.display = classId ? 'none' : '';
    document.getElementById('class-campus-group').style.display = classId ? 'none' : '';
    
    // Reset form
    document.querySelector('input[name="class_type"][value="标准班"]').checked = true;
    document.getElementById('class-name').value = '';
    document.getElementById('class-max-students').value = '';
    document.getElementById('class-lesson-hours').value = '2';
    document.querySelector('input[name="can_trial"][value="是"]').checked = true;
    updateClassCount('class-name', 'class-name-count');
    
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
    
    // Load campus dropdown
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
                updateClassCount('class-name', 'class-name-count');
            }
        } catch (e) { /* ignore */ }
    }
    
    openModal('modal-class-form');
}

async function saveClass() {
    const id = document.getElementById('edit-class-id').value;
    const courseId = parseInt(document.getElementById('class-course').value) || 0;
    const name = document.getElementById('class-name').value.trim();
    const maxStudents = parseInt(document.getElementById('class-max-students').value) || 0;
    const lessonHours = parseInt(document.getElementById('class-lesson-hours').value) || 2;
    const canTrial = document.querySelector('input[name="can_trial"]:checked').value;
    
    if (!courseId) return showToast('请选择关联课程', 'error');
    if (!name) return showToast('班级名称不能为空', 'error');
    if (name.length > 20) return showToast('班级名称最长20字', 'error');
    if (maxStudents <= 0) return showToast('招生人数必须大于0', 'error');
    if (lessonHours % 2 !== 0) return showToast('授课课时必须为偶数', 'error');

    const data = { course_id: courseId, name, max_students: maxStudents, lesson_hours: lessonHours, can_trial: canTrial };
    if (!id) {
        const campus = document.getElementById('class-campus').value;
        if (!campus) return showToast('请选择当前校区', 'error');
        data.class_type = document.querySelector('input[name="class_type"]:checked').value;
        data.campus = campus;
    }
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
    // 检测当前活跃的班级表单：考勤tab用att-前缀，独立面板用无前缀
    var prefix = document.getElementById('tab-classes') && document.getElementById('tab-classes').classList.contains('active') ? 'att-' : '';
    loadClasses(1, prefix);
    loadStats();
}

async function deleteClass(id, name) {
    showCustomConfirm('确定删除班级「' + name + '」？', async () => {
        const result = await api('delete_class', { id: id });
        if (result.error) { showToast(result.error, 'error'); return; }
        showToast(result.message);
        var prefix = document.getElementById('tab-classes') && document.getElementById('tab-classes').classList.contains('active') ? 'att-' : '';
        loadClasses(1, prefix);
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
    // 已移除按日期排课，仅保留按规则排课
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
    const periods = window._classPeriods || [];
    if (selectedDays.length === 0) {
        container.innerHTML = '<div class="schedule-time-hint">请先选择上课周期</div>';
        return;
    }
    if (periods.length === 0) {
        container.innerHTML = '<div class="schedule-time-hint">暂无预设时段，请先在基础设置中配置</div>';
        return;
    }
    const existingSelections = {};
    container.querySelectorAll('.schedule-time-row').forEach(row => {
        const dayKey = row.dataset.day;
        const sel = row.querySelector('.schedule-period-select');
        if (sel) existingSelections[dayKey] = sel.value;
    });
    container.innerHTML = selectedDays.map((name, i) => {
        const dayNum = getSelectedWeekdays()[i];
        const prevVal = existingSelections[String(dayNum)] || '';
        let opts = '<option value="">-- 选择时段 --</option>';
        periods.forEach(p => {
            const selected = String(p.id) === prevVal ? ' selected' : '';
            opts += `<option value="${p.id}" data-start="${p.start_time}" data-end="${p.end_time}"${selected}>${esc(p.name)} ${p.start_time}-${p.end_time}</option>`;
        });
        return '<div class="schedule-time-row" data-day="' + dayNum + '" style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">' +
            '<span style="min-width:40px;font-size:13px;color:#333;">' + name + '</span>' +
            '<select class="schedule-period-select" style="flex:1;padding:9px 12px;border:1px solid #E2E0E7;border-radius:8px;font-size:13px;outline:none;font-family:inherit;">' + opts + '</select>' +
        '</div>';
    }).join('');
}

function getScheduleTimeSlots() {
    const slots = {};
    document.querySelectorAll('.schedule-time-row').forEach(row => {
        const day = row.dataset.day;
        const sel = row.querySelector('.schedule-period-select');
        if (sel && sel.selectedIndex > 0) {
            const opt = sel.options[sel.selectedIndex];
            slots[day] = {
                period_id: parseInt(sel.value),
                start: opt.dataset.start,
                end: opt.dataset.end
            };
        }
    });
    return slots;
}

function setScheduleTimeSlots(slots) {
    const container = document.getElementById('schedule-time-slots');
    container.querySelectorAll('.schedule-time-row').forEach(row => {
        const day = row.dataset.day;
        const sel = row.querySelector('.schedule-period-select');
        if (!sel || !slots[day]) return;
        const slot = slots[day];
        // 优先用 period_id 匹配
        if (slot.period_id) {
            for (let i = 0; i < sel.options.length; i++) {
                if (parseInt(sel.options[i].value) === slot.period_id) {
                    sel.selectedIndex = i;
                    return;
                }
            }
        }
        // 降级用 start+end 匹配
        for (let i = 0; i < sel.options.length; i++) {
            const opt = sel.options[i];
            if (opt.dataset.start === slot.start && opt.dataset.end === slot.end) {
                sel.selectedIndex = i;
                return;
            }
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
    document.getElementById('schedule-date-range').value = '';
    document.querySelectorAll('#weekday-buttons .weekday-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('schedule-time-slots').innerHTML = '<div class="schedule-time-hint">请先选择上课周期</div>';

    // Load teacher dropdown — campus teachers first, then all others for search
    const teacherDL = document.getElementById('teacher-dropdown');
    teacherDL.innerHTML = '';
    window._allScheduleTeachers = [];
    var campusFilter = '';
    try {
        // Get class campus
        if (classId) {
            const cr = await fetch(API_BASE + 'list_classes&page=1&page_size=100');
            const cd = await cr.json();
            const cls = (cd.data || []).find(c => c.id == classId);
            if (cls) campusFilter = cls.campus || '';
        }
    } catch(e) {}
    try {
        const res = await fetch(API_BASE + 'get_employees&page=1&page_size=200');
        const data = await res.json();
        if (data.data) {
            const teachers = data.data.filter(emp => emp.is_teacher === '是');
            if (campusFilter) {
                const campusTeachers = teachers.filter(t => t.department === campusFilter);
                const otherTeachers = teachers.filter(t => t.department !== campusFilter);
                window._allScheduleTeachers = campusTeachers.concat(otherTeachers).map(e => ({ name: e.name, dept: e.department, campus: e.department === campusFilter }));
            } else {
                window._allScheduleTeachers = teachers.map(e => ({ name: e.name, dept: e.department, campus: false }));
            }
        }
    } catch (e) { /* ignore */ }

    // Load classroom dropdown — filtered by campus
    const classroomSel = document.getElementById('schedule-classroom');
    classroomSel.innerHTML = '<option value="">选择教室</option>';
    try {
        const res2 = await fetch(API_BASE + 'list_classrooms');
        const data2 = await res2.json();
        if (data2.data) {
            data2.data
                .filter(cr => !campusFilter || !cr.campus || cr.campus.indexOf(campusFilter) !== -1)
                .forEach(cr => {
                    const opt = document.createElement('option');
                    opt.value = cr.name;
                    opt.textContent = cr.name + (cr.capacity ? ' (' + cr.capacity + '人)' : '');
                    classroomSel.appendChild(opt);
                });
        }
    } catch (e) { /* ignore */ }

    // 预加载上课时段列表（按校区过滤）
    try {
        let url = API_BASE + 'list_class_periods';
        if (campusFilter) { url += '&campus=' + encodeURIComponent(campusFilter); }
        const pr = await fetch(url);
        const pd = await pr.json();
        window._classPeriods = (pd.data || []).sort((a, b) => a.start_time.localeCompare(b.start_time));
    } catch(e) { window._classPeriods = []; }

    // Initialize flatpickr BEFORE loading editing data
    initScheduleDatePickers();

    // If editing, load existing
    if (scheduleId) {
        try {
            const res3 = await fetch(API_BASE + 'get_schedule&id=' + scheduleId);
            const data3 = await res3.json();
            const sch = data3.data;
            if (sch) {
                if (sch.start_date && sch.end_date) {
                    fpRange.setDate([sch.start_date, sch.end_date], true);
                }
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
            }
        } catch (e) { /* ignore */ }
    }

    openModal('modal-schedule-form');
}

function filterTeacherDropdown() {
    var dd = document.getElementById('teacher-dropdown');
    if (!dd) return;
    var q = (document.getElementById('schedule-teacher').value || '').trim().toLowerCase();
    var list = window._allScheduleTeachers || [];
    if (list.length === 0) { dd.style.display = 'none'; return; }
    var filtered = q ? list.filter(function(t) { return t.name.toLowerCase().indexOf(q) !== -1; }) : list;
    if (filtered.length === 0) { dd.style.display = 'none'; return; }
    dd.innerHTML = filtered.map(function(t) {
        return '<div class="td-item' + (t.campus ? ' td-item-campus' : '') + '" onclick="selectTeacher(\'' + escAttr(t.name) + '\')">'
            + '<span class="td-name">' + escHtml(t.name) + '</span>'
            + (t.dept ? '<span class="td-dept">' + escHtml(t.dept) + '</span>' : '')
            + '</div>';
    }).join('');
    dd.style.display = 'block';
}

function selectTeacher(name) {
    document.getElementById('schedule-teacher').value = name;
    document.getElementById('teacher-dropdown').style.display = 'none';
}

// Close teacher dropdown on outside click
document.addEventListener('click', function(e) {
    var dd = document.getElementById('teacher-dropdown');
    var inp = document.getElementById('schedule-teacher');
    if (dd && inp && !inp.contains(e.target) && !dd.contains(e.target)) {
        dd.style.display = 'none';
    }
});

async function saveSchedule() {
    const classId = parseInt(document.getElementById('schedule-class-id').value) || 0;
    const scheduleId = parseInt(document.getElementById('edit-schedule-id').value) || 0;
    if (!classId) return showToast('班级信息缺失', 'error');

    const startDate = fpRange.selectedDates.length > 0 ? formatDate(fpRange.selectedDates[0]) : '';
    const endDate = fpRange.selectedDates.length > 1 ? formatDate(fpRange.selectedDates[1]) : '';
    const selectedWeekdays = getSelectedWeekdays();
    const timeSlots = getScheduleTimeSlots();

    if (!startDate) return showToast('请选择开课日期', 'error');
    if (!endDate) return showToast('请选择结课日期', 'error');
    if (startDate > endDate) return showToast('开课日期不能晚于结课日期', 'error');
    if (selectedWeekdays.length === 0) return showToast('请选择上课周期', 'error');
    if (Object.keys(timeSlots).length === 0) return showToast('请设置上课时段', 'error');

    let data = {
        class_id: classId,
        rule_type: '按规则排课',
        teacher: document.getElementById('schedule-teacher').value,
        classroom: document.getElementById('schedule-classroom').value,
        start_date: startDate,
        end_date: endDate,
        weekdays: selectedWeekdays.join(','),
        time_slots: JSON.stringify(timeSlots),
        holiday_enabled: 0
    };

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
// 渲染所在班级标签（方案5：暖木自然）
function renderClassTags(classNames) {
    if (!classNames) return '';
    return classNames.split(',').map(function(c) {
        var name = c.trim();
        if (!name) return '';
        return '<span class="class-tag class-tag-bg">' + esc(name) + '</span>';
    }).join('');
}

// 渲染学科剩余课时标签（方案5：暖木自然）
function renderSubjectTags(subjectRemaining) {
    if (!subjectRemaining || subjectRemaining === '-') return '';
    var subjectMap = {
        '语文': 'yuwen', '数学': 'shuxue', '英语': 'yingyu',
        '绘画': 'huihua', '书法': 'shufa'
    };
    var tags = subjectRemaining.split(',').map(function(s) {
        var colonIdx = s.indexOf(':');
        var name = colonIdx > 0 ? s.substring(0, colonIdx).trim() : s.trim();
        var hours = colonIdx > 0 ? s.substring(colonIdx + 1).trim() : '';
        if (!name) return '';
        if (hours === '0') return '';
        var cssSuffix = subjectMap[name] || 'other';
        return '<span class="subj-tag subj-tag-' + cssSuffix + '">' + esc(name) + ':' + esc(hours) + '</span>';
    }).filter(function(t) { return t !== ''; });
    return tags.length === 0 ? '' : tags.join('');
}

// 渲染授课老师标签（格式：校区:学科-老师）
function renderTeacherTags(teacherInfo) {
    if (!teacherInfo || teacherInfo === '-') return '-';
    var items = teacherInfo.split(', ');
    var tags = items.map(function(s) {
        var trimmed = s.trim();
        if (!trimmed) return '';
        return '<span class="teacher-tag">' + esc(trimmed) + '</span>';
    }).filter(function(t) { return t !== ''; });
    return tags.length === 0 ? '-' : tags.join('');
}

async function loadStudents() {
    const keyword = document.getElementById('search-student').value;
    const campus = document.getElementById('student-filter-campus')?.value || '';
    const subjectLevel1 = document.getElementById('student-filter-subject1')?.value || '';
    const studentFilter = document.getElementById('student-filter-type')?.value || '';
    const params = new URLSearchParams({ page: studentPage, page_size: 15 });
    if (keyword) params.set('keyword', keyword);
    if (campus) params.set('campus', campus);
    if (subjectLevel1) params.set('subject_level1', subjectLevel1);
    if (studentFilter) params.set('student_filter', studentFilter);
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
    tbody.innerHTML = rows.map(r => {
        return `
        <tr>
            <td style="font-family:monospace;font-size:12px;">${esc(r.student_no || '')}</td>
            <td><a class="student-name-link" href="javascript:void(0)" onclick="viewStudent(${r.id})">${esc(r.name)}</a></td>
            <td>${esc(r.phone)}</td>
            <td>${esc(r.student_type || '小课包')}</td>
            <td>${esc(r.campus || '')}</td>
            <td>${renderClassTags(r.class_names)}</td>
            <td>${renderSubjectTags(r.subject_remaining)}</td>
            <td>${renderTeacherTags(r.teacher_info)}</td>
            <td>
                <div class="action-btns">
                    <button class="btn btn-primary btn-sm" onclick="goEnroll(${r.id})">报名</button>
                    <button class="btn btn-outline-gray btn-sm" onclick="editStudent(${r.id})">编辑</button>
                </div>
            </td>
        </tr>
    `;
    }).join('');
}


async function editStudent(sid) {
    const res = await fetch(API_BASE + 'get_student&id=' + sid);
    const data = await res.json();
    if (data.error) { showToast(data.error, 'error'); return; }
    const student = data.student;
    if (!student) { showToast('未找到该学员', 'error'); return; }
    document.getElementById('edit-sid').value = student.id;
    document.getElementById('modal-student-title').textContent = '编辑学员';
    document.getElementById('student-name').value = student.name || '';
    document.getElementById('student-phone').value = student.phone || '';
    document.getElementById('student-type').value = student.student_type || '小课包';

    // 渲染已有 SST 记录
    renderSstRows(data.sst_records || []);
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
        sst_items: collectSstItems()
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

// ==================== 校区-学科-授课老师（SST）功能 ====================
let sstTeacherCache = null;
let sstRowIdCounter = 0;

async function loadSstTeachers() {
    if (sstTeacherCache) return sstTeacherCache;
    const res = await fetch(API_BASE + 'get_teachers');
    const data = await res.json();
    sstTeacherCache = data.teachers || [];
    return sstTeacherCache;
}

async function loadCampusSubjects(campusId) {
    if (!campusId) return [];
    const res = await fetch(API_BASE + 'get_campus_subjects&campus_id=' + campusId);
    const data = await res.json();
    return data.subjects || [];
}

function renderSstRows(records) {
    const container = document.getElementById('sst-rows-container');
    sstRowIdCounter = 0;
    if (!records || records.length === 0) {
        container.innerHTML = '<div style="color:#999;font-size:13px;padding:8px 0;">暂无设置</div>';
        // 预加载校区列表和老师列表
        loadSstTeachers();
        return;
    }
    container.innerHTML = '';
    loadSstTeachers().then(() => {
        records.forEach(r => {
            addSstRowWithData(r);
        });
    });
}

async function addSstRowWithData(record) {
    const rowId = ++sstRowIdCounter;
    const container = document.getElementById('sst-rows-container');
    if (container.querySelector('.sst-row-empty')) {
        container.innerHTML = '';
    }

    const row = document.createElement('div');
    row.className = 'sst-row';
    row.id = 'sst-row-' + rowId;
    row.style.cssText = 'display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap;';

    // 校区下拉
    const campusSel = document.createElement('select');
    campusSel.className = 'sst-campus';
    campusSel.style.cssText = 'flex:1;min-width:130px;';
    campusSel.innerHTML = '<option value="">选择校区</option>';
    // 加载校区列表
    try {
        const res = await fetch(API_BASE + 'list_organizations');
        const odata = await res.json();
        const orgs = odata.data ? odata.data.flat : [];
        orgs.filter(o => o.type === '校区').forEach(o => {
            campusSel.innerHTML += '<option value="' + o.id + '">' + esc(o.name) + '</option>';
        });
    } catch(e) {}
    row.appendChild(campusSel);

    // 学科下拉
    const subjSel = document.createElement('select');
    subjSel.className = 'sst-subject';
    subjSel.style.cssText = 'flex:1;min-width:160px;';
    subjSel.innerHTML = '<option value="">选择学科</option>';
    row.appendChild(subjSel);

    // 老师下拉
    const teacherSel = document.createElement('select');
    teacherSel.className = 'sst-teacher';
    teacherSel.style.cssText = 'flex:1;min-width:130px;';
    teacherSel.innerHTML = '<option value="">选择老师</option>';
    const teachers = await loadSstTeachers();
    teachers.forEach(t => {
        teacherSel.innerHTML += '<option value="' + t.id + '">' + esc(t.name) + (t.department ? ' (' + esc(t.department) + ')' : '') + '</option>';
    });
    row.appendChild(teacherSel);

    // 删除按钮
    const delBtn = document.createElement('button');
    delBtn.type = 'button';
    delBtn.className = 'btn btn-outline btn-sm';
    delBtn.style.cssText = 'color:#e74c3c;border-color:#e74c3c;';
    delBtn.textContent = '删除';
    delBtn.onclick = function() { removeSstRow(rowId); };
    row.appendChild(delBtn);

    // 校区变更时加载学科
    campusSel.onchange = async function() {
        const cid = this.value;
        subjSel.innerHTML = '<option value="">加载中...</option>';
        subjSel.disabled = true;
        try {
            const subjects = await loadCampusSubjects(cid);
            subjSel.innerHTML = '<option value="">选择学科</option>';
            subjects.forEach(s => {
                const label = s.parent_name ? s.parent_name + ' > ' + s.name : s.name;
                subjSel.innerHTML += '<option value="' + s.id + '">' + esc(label) + '</option>';
            });
        } catch(e) {
            subjSel.innerHTML = '<option value="">加载失败</option>';
        }
        subjSel.disabled = false;
    };

    container.appendChild(row);

    // 如果有预设数据，加载
    if (record) {
        campusSel.value = record.campus_id || '';
        if (record.campus_id) {
            campusSel.onchange();
            // 等待学科加载完成后设置值
            const checkSubj = setInterval(() => {
                if (subjSel.options.length > 1 && subjSel.options[0].textContent !== '加载中...') {
                    clearInterval(checkSubj);
                    subjSel.value = record.subject_id || '';
                }
            }, 100);
            setTimeout(() => clearInterval(checkSubj), 3000);
        }
        if (record.teacher_id) {
            teacherSel.value = record.teacher_id;
        }
        // 存储 SST 记录 ID（用于更新而非新增）
        row.setAttribute('data-sst-id', record.id || '');
    }
}

async function addSstRow() {
    await addSstRowWithData(null);
}

function removeSstRow(rowId) {
    const row = document.getElementById('sst-row-' + rowId);
    if (row) row.remove();
    const container = document.getElementById('sst-rows-container');
    if (!container.querySelector('.sst-row')) {
        container.innerHTML = '<div class="sst-row-empty" style="color:#999;font-size:13px;padding:8px 0;">暂无设置</div>';
    }
}

function collectSstItems() {
    const items = [];
    const rows = document.querySelectorAll('#sst-rows-container .sst-row');
    rows.forEach(row => {
        const campusSel = row.querySelector('.sst-campus');
        const subjSel = row.querySelector('.sst-subject');
        const teacherSel = row.querySelector('.sst-teacher');
        const campusId = campusSel ? parseInt(campusSel.value) || 0 : 0;
        const subjectId = subjSel ? parseInt(subjSel.value) || 0 : 0;
        const teacherId = teacherSel ? parseInt(teacherSel.value) || 0 : 0;
        if (campusId > 0 && subjectId > 0) {
            items.push({ campus_id: campusId, subject_id: subjectId, teacher_id: teacherId });
        }
    });
    return items;
}

// ==================== 学员详情 ====================
let currentViewStudentId = null;

async function viewStudent(sid) {
    currentViewStudentId = sid;
    loadCampusAndSubjects(); // 预加载校区+学科数据（充值弹窗用，不阻塞UI）
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

    // 累计汇总
    const summary = data.summary;
    if (summary) {
        document.getElementById('student-summary').style.display = 'block';
        document.getElementById('student-summary').innerHTML = `<div style="background:#f7f9fc;border:1px solid #dce3e8;border-radius:8px;padding:14px 20px;display:flex;gap:32px;font-size:14px;">
            <div><span style="color:#888;">累计报读课时：</span><b>${summary.total_lessons}</b></div>
            <div><span style="color:#888;">已消耗课时：</span><b>${summary.consumed_lessons}</b></div>
            <div><span style="color:#888;">累计报读金额：</span><b>¥${Number(summary.total_amount).toLocaleString('zh-CN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</b></div>
            <div><span style="color:#888;">已消耗金额：</span><b>¥${Number(summary.consumed_amount).toLocaleString('zh-CN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</b></div>
        </div>`;
    } else {
        document.getElementById('student-summary').style.display = 'none';
    }

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
    else if (tabId === 'tab-account') loadStudentAccount(sid);
}

// 标签页点击事件委托
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('sdt-tab')) {
        switchStudentDetailTab(e.target.dataset.tab);
    }
});

// ==================== 学员详情 - 报读课程 ====================
let studentCoursesAllRows = [];

async function loadStudentCourses(sid) {
    const container = document.getElementById('student-courses-content');
    container.innerHTML = '<div style="text-align:center;color:#999;padding:20px;">加载中...</div>';
    try {
        const res = await fetch(API_BASE + 'get_student_courses&student_id=' + sid);
        const data = await res.json();
        const rows = data.data || [];
        studentCoursesAllRows = rows;
        if (rows.length === 0) {
            container.innerHTML = '<div style="text-align:center;color:#999;padding:30px;">暂未报读课程</div>';
            return;
        }
        renderStudentCoursesFilters(rows);
        renderStudentCoursesTable(rows);
    } catch (e) {
        container.innerHTML = '<div style="text-align:center;color:#e74c3c;padding:20px;">加载失败</div>';
    }
}

function renderStudentCoursesFilters(rows) {
    const container = document.getElementById('student-courses-content');
    const subject1s = [...new Set(rows.map(r => r.subject_level1).filter(Boolean))].sort();
    // 构建一级→二级的映射
    window._subject2Map = {};
    rows.forEach(r => {
        if (r.subject_level1 && r.subject_level2) {
            if (!window._subject2Map[r.subject_level1]) window._subject2Map[r.subject_level1] = new Set();
            window._subject2Map[r.subject_level1].add(r.subject_level2);
        }
    });
    // 全部二级学科（无一级学科筛选时使用）
    const allSubject2s = [...new Set(rows.map(r => r.subject_level2).filter(Boolean))].sort();
    const renderSubject2Options = (subject1Value) => {
        const set = (subject1Value && window._subject2Map[subject1Value]) ? new Set(window._subject2Map[subject1Value]) : new Set(allSubject2s);
        return `<option value="">全部二级学科</option>` + [...set].sort().map(s => `<option value="${esc(s)}">${esc(s)}</option>`).join('');
    };
    const html = `<div style="display:flex;gap:12px;align-items:center;margin-bottom:12px;flex-wrap:wrap;">
        <select id="student-filter-subject1" onchange="onStudentSubject1Change()" style="padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:13px;">
            <option value="">全部一级学科</option>
            ${subject1s.map(s => `<option value="${esc(s)}">${esc(s)}</option>`).join('')}
        </select>
        <select id="student-filter-subject2" onchange="filterStudentCourses()" style="padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:13px;">
            ${renderSubject2Options('')}
        </select>
        <input type="text" id="filter-course-name" placeholder="搜索课程名称" oninput="filterStudentCourses()" style="padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:13px;width:180px;" autocomplete="off">
        <span style="font-size:14px;color:#888;margin-left:auto;">共 <b id="student-courses-count">${rows.length}</b> 门课程</span>
        <button class="btn btn-primary btn-sm" onclick="showClassEnrollModal(${currentViewStudentId})">分班</button>
    </div>`;
    container.innerHTML = html + '<div id="student-courses-table"></div>';
}

function onStudentSubject1Change() {
    const subject1 = document.getElementById('student-filter-subject1')?.value || '';
    const sel2 = document.getElementById('student-filter-subject2');
    const prevVal = sel2.value;
    // 根据当前一级学科更新二级下拉选项
    const allSubject2s = [...new Set(studentCoursesAllRows.map(r => r.subject_level2).filter(Boolean))].sort();
    const set = (subject1 && window._subject2Map[subject1]) ? new Set(window._subject2Map[subject1]) : new Set(allSubject2s);
    sel2.innerHTML = `<option value="">全部二级学科</option>` + [...set].sort().map(s => `<option value="${esc(s)}">${esc(s)}</option>`).join('');
    // 如果之前选中的二级还在新选项中，保留；否则重置为"全部"
    if ([...set].includes(prevVal)) {
        sel2.value = prevVal;
    }
    filterStudentCourses();
}

function onStudentFilterChange() {
    studentPage = 1;
    loadStudents();
}

async function initStudentCampusFilter() {
    const sel = document.getElementById('student-filter-campus');
    if (!sel) return;
    try {
        const res = await fetch(API_BASE + 'list_organizations');
        const data = await res.json();
        const orgs = (data && data.data && data.data.flat) ? data.data.flat : [];
        const campuses = orgs.filter(o => o.type === '校区');
        campuses.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.name;
            opt.textContent = c.name;
            sel.appendChild(opt);
        });
    } catch (e) { /* silently ignore */ }
    // 加载一级学科下拉
    initStudentSubjectFilter();
}

async function initStudentSubjectFilter() {
    const sel = document.getElementById('student-filter-subject1');
    if (!sel) return;
    try {
        const res = await fetch(API_BASE + 'list_subjects');
        const data = await res.json();
        const flat = data.flat || [];
        // 只取一级学科（parent_id=0）
        const level1 = flat.filter(s => s.parent_id === 0);
        level1.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.name;
            opt.textContent = s.name;
            sel.appendChild(opt);
        });
    } catch (e) { /* silently ignore */ }
}

function filterStudentCourses() {
    const subject1 = document.getElementById('student-filter-subject1')?.value || '';
    const subject2 = document.getElementById('student-filter-subject2')?.value || '';
    const nameKw = (document.getElementById('filter-course-name')?.value || '').trim().toLowerCase();
    let filtered = studentCoursesAllRows;
    if (subject1) filtered = filtered.filter(r => r.subject_level1 === subject1);
    if (subject2) filtered = filtered.filter(r => r.subject_level2 === subject2);
    if (nameKw) filtered = filtered.filter(r => (r.name || '').toLowerCase().includes(nameKw));
    document.getElementById('student-courses-count').textContent = filtered.length;
    renderStudentCoursesTable(filtered);
}

function renderStudentCoursesTable(rows) {
    const tableDiv = document.getElementById('student-courses-table');
    if (!tableDiv) return;
    tableDiv.innerHTML = `<div class="table-wrap"><table><thead><tr>
        <th>课程名称</th><th>校区</th><th>一级学科</th><th>二级学科</th><th>价格方案</th><th>报价单</th><th>课时数量</th><th>实际价格</th><th>已消耗课时</th><th>已消耗金额</th><th>已退课时</th><th>剩余课时</th><th>剩余金额</th><th>报名时间</th><th>子订单号</th><th>状态</th><th>操作</th>
    </tr></thead><tbody>
    ${rows.map(r => {
        const refundStatus = (r.refund_status || '正常');
        let refundStatusHtml = '';
        if (refundStatus === '已退费') {
            refundStatusHtml = '<span class="tag tag-refunded">已退费</span>';
        } else if (refundStatus === '退费申请中') {
            refundStatusHtml = '<span class="tag tag-refund-pending">退费申请中</span>';
        } else {
            refundStatusHtml = '<span class="tag tag-normal">正常</span>';
        }
        const lc = parseInt(r.lesson_count) || 0;
        const cl = parseInt(r.consumed_lessons) || 0;
        const hasRemaining = lc > cl && refundStatus === '正常';
        // 退费按钮
        let optHtml = '';
        if (hasRemaining) {
            optHtml = `<button class="btn btn-danger btn-sm" onclick="showRefundApplyModal(${r.order_id})" style="font-size:11px;padding:2px 8px;">退费</button>`;
        } else if (refundStatus === '退费申请中') {
            optHtml = '<span style="color:#999;font-size:12px;">审批中</span>';
        } else if (refundStatus === '已退费') {
            optHtml = '<span style="color:#999;font-size:12px;">已退费</span>';
        } else if (lc <= cl) {
            optHtml = '<span style="color:#999;font-size:12px;">无剩余课时</span>';
        }
        return `<tr>
        <td>${esc(r.name)}</td>
        <td>${esc(r.campus || '-')}</td>
        <td>${esc(r.subject_level1) || '-'}</td><td>${esc(r.subject_level2) || '-'}</td>
        <td>${esc(r.plan_name)}</td>
        <td>${esc(r.item_name)}</td>
        <td>${r.lesson_count || ''}</td>
        <td>${r.actual_price != null ? '¥' + Number(r.actual_price).toFixed(2) : ''}</td>
        <td>${r.consumed_lessons != null ? `<a href="javascript:void(0)" onclick="showConsumptionDetail(${r.order_id}, ${r.id}, '${esc(r.name).replace(/'/g, "\\'")}')" style="color:#1677ff;text-decoration:underline;cursor:pointer;">${r.consumed_lessons}</a>` : 0}</td>
        <td>${r.consumed_amount != null ? '¥' + Number(r.consumed_amount).toFixed(2) : '¥0.00'}</td>
        <td>${r.refunded_lessons || 0}</td>
        <td>${r.remaining_lessons != null ? r.remaining_lessons : (r.lesson_count || 0)}</td>
        <td>${r.remaining_amount != null ? '¥' + Number(r.remaining_amount).toFixed(2) : '¥0.00'}</td>
        <td>${r.created_at ? r.created_at.slice(0, 16) : ''}</td>
        <td style="font-family:monospace;font-size:12px;">${esc(r.order_no || '')}</td>
        <td>${refundStatusHtml}</td>
        <td>${optHtml}</td>
    </tr>`;
    }).join('')}
    </tbody></table></div>`;
}

// ==================== 课耗明细弹窗 ====================
function statusMap(s) {
    if (s === '缺勤') return { cls: 'cst-dot-red', bg: 'cst-badge-red' };
    if (s === '请假') return { cls: 'cst-dot-orange', bg: 'cst-badge-orange' };
    return { cls: 'cst-dot-green', bg: 'cst-badge-green' };
}

async function showConsumptionDetail(orderId, courseId, courseName) {
    document.getElementById('modal-consumption-title').textContent = '课耗明细 - ' + courseName;
    const list = document.getElementById('consumption-detail-list');
    const summary = document.getElementById('consumption-summary');
    list.innerHTML = '<div class="consumption-empty">加载中...</div>';
    summary.style.display = 'none';
    openModal('modal-consumption-detail');
    try {
        const res = await fetch(API_BASE + 'list_attendance&student_id=' + currentViewStudentId);
        const data = await res.json();
        const rows = (data.data || []).filter(r => r.order_id == orderId);
        if (rows.length === 0) {
            list.innerHTML = '<div class="consumption-empty">暂未产生课耗记录</div>';
            return;
        }
        // 汇总
        let totalLessons = 0, totalAmount = 0;
        rows.forEach(r => {
            if (r.status === '出勤') {
                totalLessons += (parseFloat(r.deducted_lessons) || 0);
                totalAmount += (parseFloat(r.consumed_amount) || 0);
            }
        });
        document.getElementById('consumption-count').textContent = '共 ' + rows.length + ' 条记录';
        document.getElementById('consumption-total-lessons').textContent = totalLessons;
        document.getElementById('consumption-total-amount').textContent = '¥' + totalAmount.toFixed(2);
        summary.style.display = 'flex';
        // 渲染卡片
        list.innerHTML = rows.map(r => {
            const st = statusMap(r.status);
            const clsTime = (r.class_time || '').replace('~', '—');
            const lessons = parseFloat(r.deducted_lessons) || 0;
            const amount = (parseFloat(r.consumed_amount) || 0).toFixed(2);
            const attTime = r.attended_at ? r.attended_at.slice(0, 16).replace('T', ' ') : '—';
            return `<div class="consumption-card">
                <div class="cst-accent ${st.cls}"></div>
                <div class="cst-body">
                    <div class="cst-main">
                        <div class="cst-date-block">
                            <span class="cst-date">${esc(r.lesson_date)}</span>
                            <span class="cst-time">${esc(clsTime)}</span>
                        </div>
                        <div class="cst-status-block">
                            <span class="cst-badge ${st.bg}">${esc(r.status)}</span>
                        </div>
                        <div class="cst-consume-block">
                            <div class="cst-consume-lessons">${lessons} <span class="cst-unit">课时</span></div>
                            <div class="cst-consume-amount">¥${amount}</div>
                        </div>
                    </div>
                    <div class="cst-meta">
                        <span>${esc(r.campus)}</span><span class="cst-sep">·</span>
                        <span>${esc(r.course_name)}</span><span class="cst-sep">·</span>
                        <span>${esc(r.class_name)}</span><span class="cst-sep">·</span>
                        <span>${esc(r.teacher)}</span>
                    </div>
                    <div class="cst-sub">
                        <span class="cst-sub-label">学科</span>
                        <span>${esc(r.subject_level1)}${r.subject_level2 ? ' > ' + esc(r.subject_level2) : ''}</span>
                        <span class="cst-sub-sep">|</span>
                        <span class="cst-sub-label">考勤</span>
                        <span>${esc(attTime)}</span>
                    </div>
                </div>
            </div>`;
        }).join('');
    } catch (e) {
        list.innerHTML = '<div class="consumption-empty" style="color:#e74c3c;">加载失败</div>';
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
            <th>订单号</th><th>父订单号</th><th>学号</th><th>编号</th><th>学员姓名</th><th>校区</th><th>一级学科</th><th>二级学科</th><th>课程名称</th><th>价格方案</th><th>报价单名称</th><th>课时数量</th><th>订单金额</th><th>现金</th><th>美团</th><th>账户</th><th>订单创建时间</th><th>订单支付时间</th><th>订单类型</th><th>支付状态</th><th>是否作废</th><th>操作</th>
        </tr></thead><tbody>
        ${rows.map(r => {
            const cash = Number(r.cash_amount) || 0;
            const mt = Number(r.meituan_amount) || 0;
            const acct = Number(r.account_amount) || 0;
            let orderTypeHtml = '';
            const ot = (r.order_type || '').trim();
            if (ot === '新报') orderTypeHtml = '<span class="tag tag-new-enroll">新报</span>';
            else if (ot === '续费') orderTypeHtml = '<span class="tag tag-renewal">续费</span>';
            else if (ot === '小课包') orderTypeHtml = '<span class="tag tag-small-pack">小课包</span>';
            else if (ot === '账户充值') orderTypeHtml = '<span class="tag tag-account-recharge">账户充值</span>';
            return `<tr>
            <td style="font-family:monospace;font-size:12px;">${esc(r.order_no || '')}</td>
            <td style="font-family:monospace;font-size:12px;">${esc(r.parent_order_no || '')}</td>
            <td style="font-family:monospace;font-size:12px;">${esc(r.student_no || '')}</td>
            <td>${r.id}</td>
            <td>${esc(r.student_name)}</td>
            <td>${esc(r.campus || '-')}</td>
            <td>${esc(r.subject_level1 || '-')}</td>
            <td>${esc(r.subject_level2 || '-')}</td>
            <td>${esc(r.course_name)}</td>
            <td>${esc(r.plan_name)}</td>
            <td>${esc(r.item_name)}</td>
            <td>${r.lesson_count || ''}</td>
            <td>${r.actual_price != null ? '¥' + Number(r.actual_price).toFixed(2) : ''}</td>
            <td>${cash > 0 ? '¥' + cash.toFixed(2) : '-'}</td>
                        <td>${mt > 0 ? '¥' + mt.toFixed(2) : '-'}</td>
                        <td>${acct > 0 ? '¥' + acct.toFixed(2) : '-'}</td>
            <td>${r.created_at ? r.created_at.slice(0, 16) : ''}</td>
            <td>${r.paid_at ? r.paid_at.slice(0, 16) : ''}</td>
            <td>${orderTypeHtml}</td>
            <td>${renderPayStatus(r.pay_status)}</td>
            <td>${renderVoidedStatus(r.is_voided)}</td>
            <td>${r.is_voided === '否' ? `<button class="btn btn-danger btn-sm" onclick="voidOrder(${r.id})" style="font-size:11px;padding:1px 6px;">作废</button>` : '-'}</td>
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
    window._courseOrderMap = {};
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
                window._courseOrderMap[c.id] = c.order_id;
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
            order_id: (window._courseOrderMap && window._courseOrderMap[courseId]) || 0,
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
            order_id: (window._courseOrderMap && window._courseOrderMap[courseId]) || 0,
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
let currentEnrollStep = 0; // 0=未开始, 1=选择课程, 2=选择方案, 3=确认支付

// ==================== 报名步骤进度条（动态创建） ====================
function ensureEnrollProgressBar() {
    if (document.getElementById('enroll-progress-bar')) return;
    const formDiv = document.querySelector('#panel-enroll .enroll-form');
    if (!formDiv) return;
    const bar = document.createElement('div');
    bar.id = 'enroll-progress-bar';
    bar.className = 'enroll-progress-bar';
    bar.innerHTML = `
        <div class="eps" data-step="1">
            <div class="eps-num">1</div>
            <span>选择课程</span>
        </div>
        <div class="eps-conn"><div class="eps-conn-inner"></div></div>
        <div class="eps" data-step="2">
            <div class="eps-num">2</div>
            <span>选择方案</span>
        </div>
        <div class="eps-conn"><div class="eps-conn-inner"></div></div>
        <div class="eps" data-step="3">
            <div class="eps-num">3</div>
            <span>确认支付</span>
        </div>`;
    formDiv.insertBefore(bar, formDiv.firstChild);
}
function setEnrollProgress(step) {
    if (currentEnrollStep === step) return;
    currentEnrollStep = step;
    ensureEnrollProgressBar();
    const bar = document.getElementById('enroll-progress-bar');
    if (!bar) return;
    bar.querySelectorAll('.eps').forEach(el => {
        const s = parseInt(el.dataset.step);
        el.classList.toggle('active', s === step);
        el.classList.toggle('done', s < step);
    });
    bar.querySelectorAll('.eps-conn').forEach((el, i) => {
        el.classList.toggle('done', i + 1 < step);
    });
    // 如果回到步骤1，重置后续
    if (step <= 1) {
        bar.querySelectorAll('.eps[data-step="2"], .eps[data-step="3"]').forEach(el => {
            el.classList.remove('done', 'active');
        });
        bar.querySelectorAll('.eps-conn').forEach(el => el.classList.remove('done'));
        bar.querySelector('.eps[data-step="1"]').classList.add('active');
    }
}

// ==================== 平滑过渡工具函数 ====================
function enrollTransitionShow(el) {
    if (!el) return;
    // 取消任何待执行的隐藏操作
    if (el._hideTimer) { clearTimeout(el._hideTimer); el._hideTimer = null; }
    if (el._hideOnEnd) { el.removeEventListener('transitionend', el._hideOnEnd); el._hideOnEnd = null; }
    el.style.display = '';
    el.style.overflow = 'hidden';
    el.style.maxHeight = '0px';
    el.style.opacity = '0';
    el.style.transition = 'max-height 0.45s cubic-bezier(0.4,0,0.2,1), opacity 0.35s ease, margin-top 0.35s ease';
    el.offsetHeight; // force reflow
    el.style.maxHeight = (el.scrollHeight + 600) + 'px';
    el.style.opacity = '1';
    // 过渡结束后移除maxHeight限制，避免内容被截断
    const onEnd = function() {
        el.style.maxHeight = 'none';
        el.style.overflow = '';
        el.removeEventListener('transitionend', onEnd);
    };
    el.addEventListener('transitionend', onEnd);
    // 兜底：0.6s后强制清除
    setTimeout(() => {
        if (el.style.maxHeight && el.style.maxHeight !== 'none') {
            el.style.maxHeight = 'none';
            el.style.overflow = '';
        }
    }, 600);
}
function enrollTransitionHide(el) {
    if (!el) return;
    // 取消之前的 hide 定时器
    if (el._hideTimer) { clearTimeout(el._hideTimer); el._hideTimer = null; }
    if (el._hideOnEnd) { el.removeEventListener('transitionend', el._hideOnEnd); el._hideOnEnd = null; }
    el.style.overflow = 'hidden';
    el.style.maxHeight = el.scrollHeight + 'px';
    el.style.opacity = '1';
    el.style.transition = 'max-height 0.3s ease, opacity 0.25s ease, margin-top 0.25s ease';
    el.offsetHeight;
    el.style.maxHeight = '0px';
    el.style.opacity = '0';
    var onEnd = function() {
        el.style.display = 'none';
        el.style.maxHeight = '';
        el.style.overflow = '';
        el.removeEventListener('transitionend', onEnd);
        el._hideOnEnd = null;
    };
    el._hideOnEnd = onEnd;
    el.addEventListener('transitionend', onEnd);
    el._hideTimer = setTimeout(function() {
        if (el.style.display !== 'none') {
            el.style.display = 'none';
            el.style.maxHeight = '';
            el.style.overflow = '';
        }
        el._hideTimer = null;
    }, 350);
}

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

    // Load account balance
    try {
        const acctRes = await fetch(API_BASE + 'get_student_account&student_id=' + studentId);
        const acctData = await acctRes.json();
        const bal = parseFloat(acctData.balance) || 0;
        document.getElementById('enroll-balance-avail').textContent = '(¥' + bal.toLocaleString('zh-CN', {minimumFractionDigits: 2}) + ')';
        document.getElementById('enroll-payment-balance').max = bal;
        document.getElementById('enroll-info-balance').textContent = '账户余额：¥' + bal.toLocaleString('zh-CN', {minimumFractionDigits: 2});
    } catch (e) {
        console.error('加载账户余额失败:', e);
        document.getElementById('enroll-info-balance').textContent = '账户余额：加载失败';
    }

    // Reset payment inputs
    document.getElementById('enroll-payment-cash').value = '0';
    document.getElementById('enroll-payment-meituan').value = '0';
    document.getElementById('enroll-payment-balance').value = '0';

    // Reset course picker
    loadEnrollCoursePicker(null);

    // Load campus list
    await loadCampusOptions('enroll-campus-select');

    // Hide plan/items sections with transition
    enrollTransitionHide(document.getElementById('enroll-plans-section'));
    enrollTransitionHide(document.getElementById('enroll-items-section'));

    setEnrollProgress(1);
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

    // Reset course picker
    loadEnrollCoursePicker(null);

    // Load campus list
    await loadCampusOptions('enroll-campus-select');

    // Update back button text
    document.getElementById('btn-enroll-back').textContent = '返回我的资源';

    // Hide plan/items sections with transition
    enrollTransitionHide(document.getElementById('enroll-plans-section'));
    enrollTransitionHide(document.getElementById('enroll-items-section'));

    setEnrollProgress(1);
    activatePanel('panel-enroll');
    highlightLeafByPanel('panel-enroll');
}

// Campus select change → load course picker
document.addEventListener('DOMContentLoaded', function() {
    const campusSel = document.getElementById('enroll-campus-select');
    if (campusSel) {
        campusSel.addEventListener('change', async function() {
            const campusId = this.value;
            currentEnrollCampusId = campusId ? parseInt(campusId) : null;
            currentEnrollCourseId = null;
            currentEnrollPlanId = null;
            // 隐藏价格方案和报价明细
            enrollTransitionHide(document.getElementById('enroll-items-section'));
            const plansSection = document.getElementById('enroll-plans-section');
            if (plansSection) plansSection.style.display = 'none';
            document.getElementById('enroll-plans-list').innerHTML = '';
            setEnrollProgress(1);
            if (!campusId) {
                loadEnrollCoursePicker(null);
                return;
            }
            await loadEnrollCoursePicker(campusId);
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
        const total = (p.items || []).reduce((s, i) => s + (parseFloat(i.actual_price) || 0), 0);
        const totalLessons = (p.items || []).reduce((s, i) => s + (parseInt(i.lesson_count) || 0), 0);
        const itemCount = (p.items || []).length;
        return `<div class="enroll-plan-card" data-plan-id="${p.id}" onclick="selectEnrollPlan(${p.id})">
            <div class="enroll-plan-top">
                <span class="enroll-plan-name">${esc(p.name)}${typeTag}</span>
                <span class="enroll-plan-price">¥${total.toFixed(2)}</span>
            </div>
            <div class="enroll-plan-meta">
                <span class="meta-chip">📋 ${itemCount} 项</span>
                <span class="meta-chip">📚 ${totalLessons} 课时</span>
            </div>
        </div>`;
    }).join('');
}

function selectEnrollPlan(planId) {
    currentEnrollPlanId = planId;
    currentEnrollPlanType = '';
    setEnrollProgress(2);

    const plan = currentEnrollPlans.find(p => p.id == planId);
    currentEnrollPlanType = (plan && plan.plan_type) ? plan.plan_type : '';

    // 高亮选中卡片
    document.querySelectorAll('.enroll-plan-card').forEach(c => c.classList.remove('active'));
    const card = document.querySelector(`.enroll-plan-card[data-plan-id="${planId}"]`);
    if (card) card.classList.add('active');

    if (!plan || !plan.items || plan.items.length === 0) {
        enrollTransitionHide(document.getElementById('enroll-items-section'));
        return;
    }

    // 渲染下方报价明细表（支付相关使用）
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

    // 平滑过渡显示报价明细区域 + 滚动
    enrollTransitionShow(document.getElementById('enroll-items-section'));
    setTimeout(() => {
        document.getElementById('enroll-items-section').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 100);

    // 初始化支付方式
    document.getElementById('enroll-payment-cash').value = total.toFixed(2);
    document.getElementById('enroll-payment-meituan').value = '0.00';
    setEnrollProgress(3);
    updatePaymentHint();
}

// ==================== 支付方式交互（智能回填） ====================
function onPaymentInput() {
    const totalText = document.getElementById('enroll-total-price').textContent.replace('¥', '');
    const total = parseFloat(totalText) || 0;
    const cashEl = document.getElementById('enroll-payment-cash');
    const meituanEl = document.getElementById('enroll-payment-meituan');
    const balanceEl = document.getElementById('enroll-payment-balance');
    const cash = parseFloat(cashEl.value) || 0;
    const meituan = parseFloat(meituanEl.value) || 0;
    const bal = parseFloat(balanceEl.value) || 0;
    const activeEl = document.activeElement;

    if (activeEl === cashEl) {
        // 用户编辑现金 → 美团自动补足（余额不变）
        const remaining = Math.max(0, total - cash - bal);
        meituanEl.value = remaining.toFixed(2);
        if (cash + bal > total) {
            cashEl.value = Math.max(0, total - bal).toFixed(2);
            meituanEl.value = '0.00';
        }
    } else if (activeEl === meituanEl) {
        // 用户编辑美团 → 现金自动补足（余额不变）
        const remaining = Math.max(0, total - meituan - bal);
        cashEl.value = remaining.toFixed(2);
        if (meituan + bal > total) {
            meituanEl.value = Math.max(0, total - bal).toFixed(2);
            cashEl.value = '0.00';
        }
    } else if (activeEl === balanceEl) {
        // 用户编辑余额 → 现金自动补足
        const remaining = Math.max(0, total - bal);
        cashEl.value = remaining.toFixed(2);
        meituanEl.value = '0.00';
        if (bal > total) {
            balanceEl.value = total.toFixed(2);
            cashEl.value = '0.00';
        }
    }

    updatePaymentHint();
}

function updatePaymentHint() {
    const cash = parseFloat(document.getElementById('enroll-payment-cash').value) || 0;
    const meituan = parseFloat(document.getElementById('enroll-payment-meituan').value) || 0;
    const bal = parseFloat(document.getElementById('enroll-payment-balance').value) || 0;
    const totalText = document.getElementById('enroll-total-price').textContent.replace('¥', '');
    const total = parseFloat(totalText) || 0;
    const hint = document.getElementById('enroll-payment-hint');
    const diff = cash + meituan + bal - total;
    if (Math.abs(diff) < 0.01) {
        hint.style.display = 'block';
        hint.className = 'enroll-payment-hint ok';
        const parts = [];
        if (bal > 0) parts.push('余额 ¥' + bal.toFixed(2));
        if (cash > 0) parts.push('现金 ¥' + cash.toFixed(2));
        if (meituan > 0) parts.push('美团 ¥' + meituan.toFixed(2));
        hint.textContent = '金额匹配' + (parts.length ? '（' + parts.join(' + ') + '）' : '');
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
    const paymentBalance = parseFloat(document.getElementById('enroll-payment-balance').value) || 0;
    const totalText = document.getElementById('enroll-total-price').textContent.replace('¥', '');
    const totalPrice = parseFloat(totalText) || 0;
    if (Math.abs(paymentCash + paymentMeituan + paymentBalance - totalPrice) > 0.01) {
        return showToast('支付金额合计（¥' + (paymentCash + paymentMeituan + paymentBalance).toFixed(2) + '）与订单总额（¥' + totalPrice.toFixed(2) + '）不一致，请调整', 'error');
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
                campus_id: currentEnrollCampusId || 0,
                use_balance: paymentBalance > 0 ? 1 : 0,
                balance_amount: paymentBalance
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
            campus_id: currentEnrollCampusId || 0,
            use_balance: paymentBalance > 0 ? 1 : 0,
            balance_amount: paymentBalance
        }, 'POST');
        if (result.error) { showToast(result.error, 'error'); return; }
        showToast(result.message);
        // 跳转至交易订单列表
        activatePanel('panel-orders');
        highlightLeafByPanel('panel-orders');
        loadOrders();
    });
}

// ==================== 报名样式注入 ====================
(function injectEnrollStyles() {
    if (document.getElementById('enroll-dynamic-styles')) return;
    const style = document.createElement('style');
    style.id = 'enroll-dynamic-styles';
    style.textContent = `
/* 步骤进度条 */
.enroll-progress-bar {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0;
    padding: 0 16px 20px 16px;
    margin-bottom: 8px;
}
.eps {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: var(--color-text-muted, #999);
    font-weight: 500;
    transition: color 0.3s;
    white-space: nowrap;
}
.eps-num {
    width: 26px; height: 26px;
    border-radius: 50%;
    border: 2px solid var(--color-border, #ddd);
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 700;
    background: var(--color-bg, #f9f9f9);
    color: var(--color-text-muted, #999);
    transition: all 0.35s cubic-bezier(0.4,0,0.2,1);
    flex-shrink: 0;
}
.eps.active { color: var(--color-primary, #7C3AED); }
.eps.active .eps-num {
    border-color: var(--color-primary, #7C3AED);
    background: var(--color-primary, #7C3AED);
    color: #fff;
    box-shadow: 0 2px 8px rgba(124,58,237,0.25);
}
.eps.done { color: var(--color-primary, #7C3AED); }
.eps.done .eps-num {
    border-color: var(--color-primary, #7C3AED);
    background: var(--color-primary-bg, #f5f0ff);
    color: var(--color-primary, #7C3AED);
}
.eps-conn {
    flex: 1;
    min-width: 24px; max-width: 60px;
    height: 2px;
    background: var(--color-border-light, #eee);
    margin: 0 4px;
    transition: background 0.35s;
    position: relative;
    overflow: hidden;
}
.eps-conn.done { background: var(--color-primary, #7C3AED); }

/* 方案卡片增强 */
.enroll-plan-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.enroll-plan-price {
    font-size: 18px;
    font-weight: 700;
    color: var(--color-primary, #7C3AED);
    letter-spacing: -0.3px;
    white-space: nowrap;
    font-feature-settings: "tnum";
}
.enroll-plan-meta {
    display: flex;
    gap: 10px;
    font-size: 12px;
    color: var(--color-text-muted, #999);
    margin-top: 4px;
}
`;
    document.head.appendChild(style);
})();

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

// ==================== 课程搜索+筛选Picker ====================
let enrollCoursePickerData = [];
let enrollCoursePickerSelected = null;
let pickerSearchEl, pickerChipsEl, pickerListEl, pickerHiddenEl, pickerBodyEl;

function initEnrollCoursePickerRefs() {
    pickerSearchEl = document.getElementById('enroll-course-search');
    pickerChipsEl  = document.getElementById('enroll-course-chips');
    pickerListEl   = document.getElementById('enroll-course-list');
    pickerHiddenEl = document.getElementById('enroll-course-id');
    pickerBodyEl   = document.querySelector('.course-picker-body');
    if (pickerSearchEl) {
        pickerSearchEl.addEventListener('input', function() {
            clearTimeout(this._debounce);
            this._debounce = setTimeout(applyCoursePickerFilters, 300);
        });
    }
}

async function loadEnrollCoursePicker(campusId) {
    if (!pickerSearchEl) initEnrollCoursePickerRefs();

    enrollCoursePickerData = [];
    enrollCoursePickerSelected = null;
    pickerHiddenEl.value = '';
    pickerSearchEl.value = '';
    pickerChipsEl.innerHTML = '';
    pickerBodyEl.classList.remove('has-selection');

    if (!campusId) {
        pickerSearchEl.disabled = true;
        pickerSearchEl.placeholder = '请先选择校区';
        pickerListEl.innerHTML = '<div class="course-picker-empty">请先选择校区</div>';
        return;
    }

    pickerSearchEl.disabled = true;
    pickerSearchEl.placeholder = '加载中...';
    pickerListEl.innerHTML = '<div class="course-picker-empty">加载课程中...</div>';

    try {
        const res = await fetch(API_BASE + 'list_courses&page=1&page_size=200&campus_id=' + campusId);
        const data = await res.json();
        enrollCoursePickerData = data.data || [];

        pickerSearchEl.disabled = false;
        pickerSearchEl.placeholder = '搜索课程名称...';

        renderCoursePickerChips(enrollCoursePickerData);
        renderCoursePickerCards(enrollCoursePickerData);
    } catch (e) {
        pickerSearchEl.disabled = true;
        pickerSearchEl.placeholder = '加载失败';
        pickerListEl.innerHTML = '<div class="course-picker-empty" style="color:#E53E3E;">加载失败，请重试</div>';
    }
}

function renderCoursePickerChips(courses) {
    const subjectMap = {};
    courses.forEach(c => {
        const s = c.subject_level1 || '未分类';
        subjectMap[s] = (subjectMap[s] || 0) + 1;
    });
    const subjects = Object.keys(subjectMap).sort();

    let html = '<span class="course-picker-chip active" data-subject="">全部（' + courses.length + '）</span>';
    subjects.forEach(s => {
        html += '<span class="course-picker-chip" data-subject="' + esc(s) + '">' + esc(s) + '<span class="chip-count">' + subjectMap[s] + '</span></span>';
    });
    pickerChipsEl.innerHTML = html;

    pickerChipsEl.querySelectorAll('.course-picker-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            pickerChipsEl.querySelectorAll('.course-picker-chip').forEach(c => c.classList.remove('active'));
            chip.classList.add('active');
            applyCoursePickerFilters();
        });
    });
}

function applyCoursePickerFilters() {
    const keyword = (pickerSearchEl.value || '').trim().toLowerCase();
    const activeChip = pickerChipsEl.querySelector('.course-picker-chip.active');
    const subjectFilter = activeChip ? activeChip.dataset.subject : '';

    let filtered = enrollCoursePickerData;
    if (subjectFilter) {
        filtered = filtered.filter(c => (c.subject_level1 || '未分类') === subjectFilter);
    }
    if (keyword) {
        filtered = filtered.filter(c =>
            (c.name || '').toLowerCase().includes(keyword) ||
            (c.subject_level1 || '').toLowerCase().includes(keyword) ||
            (c.subject_level2 || '').toLowerCase().includes(keyword)
        );
    }
    // 筛选变化时清空之前选中的课程和价格方案
    enrollCoursePickerSelected = null;
    currentEnrollCourseId = null;
    currentEnrollPlanId = null;
    pickerHiddenEl.value = '';
    pickerBodyEl.classList.remove('has-selection');
    enrollTransitionHide(document.getElementById('enroll-items-section'));
    const plansSection = document.getElementById('enroll-plans-section');
    if (plansSection) plansSection.style.display = 'none';
    document.getElementById('enroll-plans-list').innerHTML = '';
    setEnrollProgress(2);
    renderCoursePickerCards(filtered);
}

function renderCoursePickerCards(courses) {
    if (courses.length === 0) {
        pickerListEl.innerHTML = '<div class="course-picker-empty">未找到匹配的课程</div>';
        return;
    }
    pickerListEl.innerHTML = courses.map(c => {
        const selected = enrollCoursePickerSelected === c.id;
        const subjectText = [c.subject_level1, c.subject_level2].filter(Boolean).join(' / ') || '未分类';
        return '<div class="course-picker-card' + (selected ? ' selected' : '') + '" data-course-id="' + c.id + '" data-course-name="' + esc(c.name) + '" onclick="selectEnrollCourse(' + c.id + ', \'' + esc(c.name) + '\')">' +
            '<div class="course-card-info">' +
                '<div class="course-card-name">' + esc(c.name) + '</div>' +
                '<div class="course-card-subject">' + esc(subjectText) + '</div>' +
            '</div>' +
            '<div class="course-card-check">\u2713</div>' +
        '</div>';
    }).join('');
}

function selectEnrollCourse(courseId, courseName) {
    enrollCoursePickerSelected = courseId;
    currentEnrollCourseId = courseId;
    currentEnrollPlanId = null;
    pickerHiddenEl.value = courseId;
    pickerBodyEl.classList.add('has-selection');

    pickerListEl.querySelectorAll('.course-picker-card').forEach(card => {
        card.classList.toggle('selected', parseInt(card.dataset.courseId) === courseId);
    });

    setEnrollProgress(2);
    enrollTransitionHide(document.getElementById('enroll-items-section'));
    const plansSection = document.getElementById('enroll-plans-section');
    plansSection.style.display = '';
    const listDiv = document.getElementById('enroll-plans-list');
    listDiv.innerHTML = '<div style="color:#999;padding:12px;">加载中...</div>';

    fetch(API_BASE + 'get_course_plans&course_id=' + courseId)
        .then(res => res.json())
        .then(data => {
            currentEnrollPlans = data.data || [];
            renderEnrollPlansList(currentEnrollPlans);
            enrollTransitionShow(plansSection);
        })
        .catch(() => {
            listDiv.innerHTML = '<div style="color:#e74c3c;padding:12px;">加载失败</div>';
        });
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
    const payStatus = document.getElementById('filter-pay-status')?.value || '';
    const isVoided = document.getElementById('filter-is-voided')?.value || '';
    const region = document.getElementById('filter-order-region')?.value || '';
    const campusSel = document.getElementById('filter-order-campus');
    const campusVal = campusSel?.value || '';
    const payDateStart = document.getElementById('filter-pay-date-start')?.value || '';
    const payDateEnd = document.getElementById('filter-pay-date-end')?.value || '';

    // 构建 campus 参数：如果选了具体校区直接用；如果只选了区域，收集该区域下所有校区
    let campusParam = campusVal;
    if (!campusVal && region) {
        campusParam = orderCampusData
            .filter(c => c.region === region)
            .map(c => c.name)
            .join(',');
    }

    const params = new URLSearchParams({ page: orderPage, page_size: 15 });
    if (keyword) params.set('keyword', keyword);
    if (payStatus) params.set('pay_status', payStatus);
    if (isVoided) params.set('is_voided', isVoided);
    if (campusParam) params.set('campus', campusParam);
    if (payDateStart) params.set('pay_date_start', payDateStart);
    if (payDateEnd) params.set('pay_date_end', payDateEnd);
    const res = await fetch(API_BASE + 'list_orders&' + params);
    const data = await res.json();
    renderOrderTable(data.data);
    renderPagination('pagination-order', data.total, orderPage, 15, (p) => { orderPage = p; loadOrders(); });
    document.getElementById('stat-orders-inline').textContent = data.total || 0;
    renderPaymentSummary(data.payment_summary || []);
    initOrderTableScrollSync();
}

function renderPayStatus(status) {
    const s = (status || '').trim();
    if (s === '已支付') return '<span style="color:#27ae60;font-weight:bold;">已支付</span>';
    if (s === '待支付') return '<span style="color:#e67e22;font-weight:bold;">待支付</span>';
    if (s === '已取消') return '<span style="color:#999;">已取消</span>';
    return '<span style="color:#999;">-</span>';
}
function renderVoidedStatus(status) {
    const s = (status || '').trim();
    if (s === '是') return '<span style="color:#e74c3c;font-weight:bold;">是</span>';
    return s || '否';
}

function renderOrderTable(rows) {
    const tbody = document.querySelector('#table-orders tbody');
    const tfoot = document.getElementById('table-orders-foot');
    if (!rows || rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="22" style="text-align:center;color:#999;padding:30px;">暂无订单数据</td></tr>';
        tfoot.style.display = 'none';
        return;
    }
    let totalCash = 0, totalMeituan = 0, totalAccount = 0;
        tbody.innerHTML = rows.map(r => {
            const cash = Number(r.cash_amount) || 0;
            const mt = Number(r.meituan_amount) || 0;
            const acct = Number(r.account_amount) || 0;
            totalCash += cash;
            totalMeituan += mt;
            totalAccount += acct;
        let orderTypeHtml = '';
        const ot = (r.order_type || '').trim();
        if (ot === '新报') orderTypeHtml = '<span class="tag tag-new-enroll">新报</span>';
        else if (ot === '续费') orderTypeHtml = '<span class="tag tag-renewal">续费</span>';
        else if (ot === '小课包') orderTypeHtml = '<span class="tag tag-small-pack">小课包</span>';
        else if (ot === '账户充值') orderTypeHtml = '<span class="tag tag-account-recharge">账户充值</span>';
        return `
        <tr>
            <td style="font-family:monospace;font-size:12px;">${esc(r.order_no || '')}</td>
            <td style="font-family:monospace;font-size:12px;">${esc(r.parent_order_no || '')}</td>
            <td style="font-family:monospace;font-size:12px;">${esc(r.student_no || '')}</td>
            <td>${r.id}</td>
            <td>${esc(r.student_name)}</td>
            <td>${esc(r.campus || '-')}</td>
            <td>${esc(r.subject_level1 || '-')}</td>
            <td>${esc(r.subject_level2 || '-')}</td>
            <td>${esc(r.course_name)}</td>
            <td>${esc(r.plan_name)}</td>
            <td>${esc(r.item_name)}</td>
            <td>${r.lesson_count || ''}</td>
            <td>${r.actual_price != null ? '¥' + Number(r.actual_price).toFixed(2) : ''}</td>
            <td>${cash > 0 ? '¥' + cash.toFixed(2) : '-'}</td>
                        <td>${mt > 0 ? '¥' + mt.toFixed(2) : '-'}</td>
                        <td>${acct > 0 ? '¥' + acct.toFixed(2) : '-'}</td>
            <td>${r.created_at ? r.created_at.slice(0, 16) : ''}</td>
            <td>${r.paid_at ? r.paid_at.slice(0, 16) : ''}</td>
            <td>${orderTypeHtml}</td>
            <td>${renderPayStatus(r.pay_status)}</td>
            <td>${renderVoidedStatus(r.is_voided)}</td>
            <td>${r.is_voided === '否' ? `<button class="btn btn-danger btn-sm" onclick="voidOrder(${r.id})" style="font-size:11px;padding:1px 6px;">作废</button>` : '-'}</td>
        </tr>`;
    }).join('');
    tfoot.innerHTML = `<tr>
            <td colspan="14" style="text-align:right;font-weight:bold;">合计</td>
            <td style="font-weight:bold;color:#7c3aed;">¥${totalCash.toFixed(2)}</td>
            <td style="font-weight:bold;color:#7c3aed;">¥${totalMeituan.toFixed(2)}</td>
            <td style="font-weight:bold;color:#7c3aed;">¥${totalAccount.toFixed(2)}</td>
            <td colspan="5"></td>
        </tr>`;
    tfoot.style.display = '';
    syncOrderTableScrollWidth();
}

async function voidOrder(orderId) {
    if (!confirm('确定作废该订单吗？作废后该笔订单对应的报读课程将被删除。')) return;
    try {
        const res = await fetch(API_BASE + 'void_order', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId })
        });
        const data = await res.json();
        if (data.success) {
            showToast('订单作废成功');
            loadOrders();
        } else {
            showToast(data.message || '作废失败');
        }
    } catch (e) {
        showToast('网络错误，请重试');
    }
}

function renderPaymentSummary(summary) {
    const el = document.getElementById('payment-summary-order');
    if (!summary || (summary.cash_total === undefined && summary.meituan_total === undefined)) {
        el.style.display = 'none';
        return;
    }
    const cash = Number(summary.cash_total) || 0;
    const mt = Number(summary.meituan_total) || 0;
    const acct = Number(summary.account_total) || 0;
    const total = cash + mt + acct;
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
        <div class="payment-summary-card">
            <span class="payment-summary-label">账户</span>
            <span class="payment-summary-amount">¥${acct.toFixed(2)}</span>
        </div>
        <div class="payment-summary-card payment-summary-total">
            <span class="payment-summary-label">总计</span>
            <span class="payment-summary-amount">¥${total.toFixed(2)}</span>
        </div>
    </div>`;
    el.style.display = 'block';
}

// ==================== 订单表格水平滚动联动 ====================
function initOrderTableScrollSync() {
    const topScroll = document.querySelector('#panel-orders .table-scroll-top');
    const bodyScroll = document.querySelector('#panel-orders .table-scroll-body');
    if (!topScroll || !bodyScroll) return;
    topScroll.onscroll = function() { bodyScroll.scrollLeft = this.scrollLeft; };
    bodyScroll.onscroll = function() { topScroll.scrollLeft = this.scrollLeft; };
}

function syncOrderTableScrollWidth() {
    const table = document.getElementById('table-orders');
    const inner = document.querySelector('#panel-orders .table-scroll-top-inner');
    if (table && inner) {
        inner.style.width = table.scrollWidth + 'px';
    }
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

async function switchAttendanceTab(tabId) {
    document.querySelectorAll('.att-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === tabId));
    document.querySelectorAll('.att-panel').forEach(p => p.classList.toggle('active', p.id === tabId));
    if (tabId === 'tab-classes') {
        loadClasses(1, 'att-');
    } else if (tabId === 'tab-attendance-operations') {
        loadAttendanceSessions();
    } else if (tabId === 'tab-student-consumption') {
        loadStudentConsumption();
    } else if (tabId === 'tab-absence-records') {
        loadAbsenceRecords();
    } else if (tabId === 'tab-schedule-view') {
        scheduleWeekOffset = 0;
        await initScheduleCampusFilter();
        loadScheduleView();
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

let addStudentFromAttendance = false;

async function showAddStudentToAttendanceModal() {
    if (!currentAttendanceClassId) return showToast('班级信息缺失', 'error');
    addStudentFromAttendance = true;
    currentClassDetailId = currentAttendanceClassId;
    document.getElementById('modal-add-student-title').textContent = '添加学员到考勤';
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
    // 若从考勤弹窗添加，同步刷新考勤列表
    if (addStudentFromAttendance) {
        addStudentFromAttendance = false;
        reloadAttendanceSession();
    }
}

async function removeStudentFromClass(csId, studentName) {
    showCustomConfirm(`确定将学员「${studentName}」移出此班级？`, async () => {
        const result = await api('remove_class_student', { id: csId });
        if (result.error) { showToast(result.error, 'error'); return; }
        if (result.history_count > 0) {
            showToast(`该学员有 ${result.history_count} 条历史考勤记录，出班后不再参与未来课次`);
        } else {
            showToast(result.message);
        }
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
            const isTemp = r.is_temporary || 0;
            const tempBadge = isTemp ? ' <span style="color:#e74c3c;font-size:11px;font-weight:bold;">临时</span>' : '';
            return `<tr data-is-temp="${isTemp}">
                <td>${esc(r.student_no)}</td>
                <td>${esc(r.student_name)}${tempBadge}</td>
                <td><select class="ca-status-select" data-sid="${r.student_id}">
                    <option value="" ${currentStatus === '' || currentStatus === null ? 'selected' : ''} disabled>未选择</option>
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
        const tr = sel.closest('tr');
        const isTemp = tr ? parseInt(tr.dataset.isTemp || '0') : 0;
        const nameCell = tr ? tr.children[1] : null;
        const studentName = nameCell ? nameCell.textContent.replace('临时', '').trim() : '';
        const record = {
            student_id: parseInt(sel.dataset.sid),
            status: sel.value,
            student_name: studentName
        };
        if (isTemp === 1) {
            record.is_temporary = 1;
            record.student_name = studentName;
        }
        records.push(record);
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

// ==================== 添加临时学员 ====================

async function showTempStudentModal(keyword = '') {
    // 当 modal-attendance-session 活跃时，隐藏 input 可能来自旧弹窗，优先使用全局变量
    const sessionModal = document.getElementById('modal-attendance-session');
    const useGlobals = sessionModal && sessionModal.classList.contains('show');
    const classId = (useGlobals ? currentAttendanceClassId : parseInt(document.getElementById('ca-class-id')?.value) || currentAttendanceClassId);
    const scheduleId = (useGlobals ? currentAttendanceScheduleId : parseInt(document.getElementById('ca-schedule-id')?.value) || currentAttendanceScheduleId);
    const sessionDate = (useGlobals ? currentAttendanceSessionDate : document.getElementById('ca-session-date')?.value || currentAttendanceSessionDate);
    if (!classId) { showToast('班级ID无效', 'error'); return; }
    openModal('modal-temp-student');
    const tbody = document.getElementById('temp-student-tbody');
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#999;">加载中...</td></tr>';
    try {
        const res = await fetch(API_BASE + 'get_temp_student_candidates&class_id=' + classId + '&schedule_id=' + scheduleId + '&session_date=' + encodeURIComponent(sessionDate) + '&keyword=' + encodeURIComponent(keyword));
        const data = await res.json();
        const rows = data.data || [];
        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#999;padding:20px;">暂无符合条件的学员</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(r => {
            return `<tr>
                <td>${esc(r.student_no)}</td>
                <td>${esc(r.name)}</td>
                <td>${esc(r.phone || '')}</td>
                <td>${r.remaining_lessons}</td>
                <td><button class="btn btn-sm btn-primary" onclick="addTempStudentToSession(${r.id}, '${esc(r.student_no)}', '${esc(r.name)}', ${r.remaining_lessons})">添加</button></td>
            </tr>`;
        }).join('');
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#e74c3c;padding:20px;">加载失败</td></tr>';
    }
}

let tempStudentSearchTimer = null;
function onTempStudentSearch() {
    clearTimeout(tempStudentSearchTimer);
    const keyword = document.getElementById('temp-student-search')?.value || '';
    tempStudentSearchTimer = setTimeout(() => showTempStudentModal(keyword), 300);
}

function addTempStudentToSession(studentId, studentNo, studentName, remainingLessons) {
    // 判断当前活跃的是哪个考勤弹窗，获取上下文参数
    const sessionModal = document.getElementById('modal-attendance-session');
    const classModal = document.getElementById('modal-class-attendance');
    const isSessionModal = sessionModal && sessionModal.classList.contains('show');
    const useGlobals = isSessionModal;
    const classId = useGlobals ? currentAttendanceClassId : parseInt(document.getElementById('ca-class-id')?.value) || currentAttendanceClassId;
    const scheduleId = useGlobals ? currentAttendanceScheduleId : parseInt(document.getElementById('ca-schedule-id')?.value) || currentAttendanceScheduleId;
    const sessionDate = useGlobals ? currentAttendanceSessionDate : document.getElementById('ca-session-date')?.value || currentAttendanceSessionDate;

    if (isSessionModal) {
        // 新弹窗 modal-attendance-session 的行结构
        const tbody = document.getElementById('as-attendance-tbody');
        const existing = tbody.querySelector('tr[data-sid="' + studentId + '"]');
        if (existing) { showToast('该学员已在考勤列表中', 'error'); return; }
        const row = document.createElement('tr');
        row.setAttribute('data-sid', studentId);
        row.setAttribute('data-is-temp', '1');
        row.id = 'att-row-' + studentId;
        row.innerHTML = `
            <td><span style="color:var(--color-text-muted);font-size:12px;">-</span></td>
            <td><span style="font-weight:500;">${esc(studentName)}</span> <span style="color:#e74c3c;font-size:11px;font-weight:bold;">临时</span></td>
            <td><span style="color:var(--color-text-secondary);">-</span></td>
            <td style="text-align:center;"><span style="font-weight:600;color:var(--color-success)">${remainingLessons}</span></td>
            <td>
                <span class="att-deduct-stepper disabled">
                    <button class="stepper-btn" onclick="attDeductChange(this, -1)">−</button>
                    <span class="stepper-val" data-sid="${studentId}" data-lesson-hours="2" data-max="${remainingLessons}">0</span>
                    <button class="stepper-btn" onclick="attDeductChange(this, 1)">+</button>
                </span>
            </td>
            <td>
                <span class="att-status-group" data-sid="${studentId}">
                    <span class="att-status-chip" data-val="出勤" onclick="attStatusToggle(this, '出勤')">出勤</span>
                    <span class="att-status-chip" data-val="缺勤" onclick="attStatusToggle(this, '缺勤')">缺勤</span>
                </span>
            </td>
        `;
        tbody.appendChild(row);
        document.getElementById('as-total-count').textContent = tbody.querySelectorAll('tr').length;
        // 立即持久化，确保关闭弹窗再打开时临时学员仍在
        api('save_temp_attendance', { class_id: classId, schedule_id: scheduleId, session_date: sessionDate, student_id: studentId });
        closeModal('modal-temp-student');
        return;
    }

    // 旧弹窗 modal-class-attendance 的行结构
    const tbody = document.getElementById('ca-attendance-tbody');
    // 检查是否已存在
    const existing = tbody.querySelector('.ca-status-select[data-sid="' + studentId + '"]');
    if (existing) {
        showToast('该学员已在考勤列表中', 'error');
        return;
    }
    const row = document.createElement('tr');
    row.setAttribute('data-is-temp', '1');
    row.innerHTML = `
        <td>${esc(studentNo)}</td>
        <td>${esc(studentName)} <span style="color:#e74c3c;font-size:11px;font-weight:bold;">临时</span></td>
        <td><select class="ca-status-select" data-sid="${studentId}">
            <option value="出勤" selected>出勤</option>
            <option value="请假">请假</option>
            <option value="缺勤">缺勤</option>
        </select></td>
        <td class="ca-deducted-display" data-sid="${studentId}" data-lesson-hours="2">扣2课时</td>
    `;
    tbody.appendChild(row);
    // 绑定状态变更事件
    const sel = row.querySelector('.ca-status-select');
    sel.addEventListener('change', function() {
        const display = row.querySelector('.ca-deducted-display');
        if (display) {
            const lh = parseInt(display.dataset.lessonHours) || 1;
            display.textContent = this.value === '出勤' ? '扣' + lh + '课时' : '0';
        }
    });
    // 立即持久化，确保关闭弹窗再打开时临时学员仍在
    api('save_temp_attendance', { class_id: classId, schedule_id: scheduleId, session_date: sessionDate, student_id: studentId });
    closeModal('modal-temp-student');
    showToast('已添加临时学员：' + studentName);
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
    const classNameEl = document.getElementById('attendance-class-name');
    const className = classNameEl ? classNameEl.value.trim() : '';
    tbody.innerHTML = '<tr><td colspan="12" style="text-align:center;color:#999;padding:30px;">加载中...</td></tr>';
    try {
        let url = API_BASE + 'list_attendance_sessions&page=' + page + '&page_size=20';
        if (dateFrom) url += '&date_from=' + encodeURIComponent(dateFrom);
        if (dateTo) url += '&date_to=' + encodeURIComponent(dateTo);
        if (className) url += '&class_name=' + encodeURIComponent(className);
        const res = await fetch(url);
        const data = await res.json();
        const sessions = data.data || [];
        const total = data.total || 0;
        if (sessions.length === 0) {
            tbody.innerHTML = '<tr><td colspan="12" style="text-align:center;color:#999;padding:30px;">暂无排课记录</td></tr>';
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
                <td>${esc(s.course_subject_level1 || '-')}</td>
                <td>${esc(s.course_subject_level2 || '-')}</td>
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
        tbody.innerHTML = '<tr><td colspan="12" style="text-align:center;color:#e74c3c;padding:20px;">加载失败</td></tr>';
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
            const isTemp = r.is_temporary || 0;
            const tempBadge = isTemp ? ' <span style="color:#e74c3c;font-size:11px;font-weight:bold;">临时</span>' : '';
            const removeCell = isTemp
                ? '<span style="color:var(--color-text-muted);font-size:12px;">-</span>'
                : `<button class="btn-remove-att" onclick="removeAttendanceStudent(${r.student_id}, ${r.cs_id})" title="移除此学员">移除</button>`;
            return `<tr id="att-row-${r.student_id}" data-cs-id="${r.cs_id}" data-is-temp="${isTemp}">
                <td>${removeCell}</td>
                <td><span style="font-weight:500;">${esc(r.student_name)}${tempBadge}</span></td>
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

async function reloadAttendanceSession() {
    const tbody = document.getElementById('as-attendance-tbody');
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--color-text-muted);padding:28px;">正在刷新...</td></tr>';
    try {
        const res = await fetch(API_BASE + 'get_class_attendance&class_id=' + currentAttendanceClassId + '&schedule_id=' + currentAttendanceScheduleId + '&session_date=' + currentAttendanceSessionDate);
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
            if (maxDeductible > 0 && deducted > maxDeductible) deducted = maxDeductible;
            const remaining = r.remaining_lessons || 0;
            const isTemp = r.is_temporary || 0;
            const tempBadge = isTemp ? ' <span style="color:#e74c3c;font-size:11px;font-weight:bold;">临时</span>' : '';
            const removeCell = isTemp
                ? '<span style="color:var(--color-text-muted);font-size:12px;">-</span>'
                : `<button class="btn-remove-att" onclick="removeAttendanceStudent(${r.student_id}, ${r.cs_id})" title="移除此学员">移除</button>`;
            return `<tr id="att-row-${r.student_id}" data-cs-id="${r.cs_id}" data-is-temp="${isTemp}">
                <td>${removeCell}</td>
                <td><span style="font-weight:500;">${esc(r.student_name)}${tempBadge}</span></td>
                <td><span style="color:var(--color-text-secondary);">${esc(r.course_name || '')}</span></td>
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
        document.querySelectorAll('#as-attendance-tbody tr').forEach(row => {
            const activeChip = row.querySelector('.att-status-chip.active');
            if (activeChip) return;
            const stepper = row.querySelector('.att-deduct-stepper');
            const valSpan = stepper ? stepper.querySelector('.stepper-val') : null;
            if (stepper && valSpan) {
                valSpan.textContent = '0';
                stepper.classList.add('disabled');
            }
        });
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--color-danger);padding:20px;">刷新失败</td></tr>';
    }
}

async function removeAttendanceStudent(studentId, csId) {
    if (!csId || csId <= 0) return;
    const result = await api('remove_class_student', { id: csId, session_date: currentAttendanceSessionDate });
    if (result.error) { showToast(result.error, 'error'); return; }
    if (result.history_count > 0) {
        showToast(`该学员有 ${result.history_count} 条历史考勤记录，出班后不再参与未来课次`);
    }
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
        const record = { student_id: studentId, status: status, deducted_lessons: deducted };
        if (parseInt(row.dataset.isTemp || '0') === 1) record.is_temporary = 1;
        records.push(record);
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
    const searchInput = document.getElementById('temp-student-search');
    if (searchInput) searchInput.value = '';
    showTempStudentModal();
}

function addMakeupStudent() {
    showToast('添加补课学员功能开发中');
}

// ==================== 考勤模块 - 缺勤记录 ====================
let absenceRecordPage = 1;

async function loadAbsenceRecords(page = 1) {
    absenceRecordPage = page;
    const tbody = document.querySelector('#table-absence-records tbody');
    const pagination = document.getElementById('pagination-absence-records');
    tbody.innerHTML = '<tr><td colspan="11" style="text-align:center;color:#999;padding:20px;">加载中...</td></tr>';
    const dateFrom = document.getElementById('absence-date-from').value;
    const dateTo = document.getElementById('absence-date-to').value;
    const className = document.getElementById('absence-class-name').value;
    let url = API_BASE + 'list_absence_records&page=' + page + '&page_size=20';
    if (dateFrom) url += '&date_from=' + dateFrom;
    if (dateTo) url += '&date_to=' + dateTo;
    if (className) url += '&class_name=' + encodeURIComponent(className);
    try {
        const res = await fetch(url);
        const data = await res.json();
        const rows = data.data || [];
        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="11" style="text-align:center;color:#999;padding:30px;">暂无缺勤记录</td></tr>';
            pagination.innerHTML = '';
            return;
        }
        tbody.innerHTML = rows.map(r => {
            return `<tr>
                <td>${esc(r.student_name)}</td>
                <td>${esc(r.student_no || '')}</td>
                <td>${esc(r.phone)}</td>
                <td>${esc(r.campus)}</td>
                <td>${esc(r.course_name || '')}</td>
                <td>${esc(r.subject_level1)}</td>
                <td>${esc(r.subject_level2)}</td>
                <td>${esc(r.class_name)}</td>
                <td>${esc(r.teacher)}</td>
                <td>${esc(r.lesson_date)}</td>
                <td>${esc(r.class_time)}</td>
            </tr>`;
        }).join('');
        const total = data.total || 0;
        const totalPages = Math.ceil(total / (data.page_size || 20));
        pagination.innerHTML = '<span>共 ' + total + ' 条</span>' +
            (page > 1 ? '<button class="btn btn-sm btn-outline" onclick="loadAbsenceRecords(' + (page - 1) + ')">上一页</button>' : '') +
            '<span>第 ' + page + '/' + totalPages + ' 页</span>' +
            (page < totalPages ? '<button class="btn btn-sm btn-outline" onclick="loadAbsenceRecords(' + (page + 1) + ')">下一页</button>' : '');
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="11" style="text-align:center;color:#e74c3c;padding:20px;">加载失败</td></tr>';
    }
}


// ==================== 退费管理 ====================

// 工作记录标签页初始化
function initWorkRecordTabs() {
    document.querySelectorAll('#panel-work-records .sec-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('#panel-work-records .sec-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('#panel-work-records .sec-panel').forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            const targetId = this.dataset.tab;
            const target = document.getElementById(targetId);
            if (target) target.classList.add('active');
            if (targetId === 'tab-refund-records') {
                refundPage = 1;
                loadRefundRecords();
            }
        });
    });
}

// ==================== 退费申请（学员详情页） ====================
let refundApplyRemainingAmount = 0;

async function showRefundApplyModal(orderId) {
    // 从当前学员课程数据中找到目标订单
    const orderRow = studentCoursesAllRows.find(r => r.order_id == orderId);
    if (!orderRow) { showToast('未找到订单信息', 'error'); return; }
    
    const cl = parseInt(orderRow.consumed_lessons) || 0;
    const lc = parseInt(orderRow.lesson_count) || 0;
    const ap = parseFloat(orderRow.actual_price) || 0;
    const remainingLessons = lc - cl;
    const remainingAmount = lc > 0 ? (ap * remainingLessons / lc) : 0;
    refundApplyRemainingAmount = remainingAmount;

    document.getElementById('refund-apply-order-id').value = orderId;
    document.getElementById('refund-auto-campus').textContent = orderRow.campus || '-';
    document.getElementById('refund-auto-course').textContent = orderRow.name || '-';
    document.getElementById('refund-auto-total-lessons').textContent = lc;
    document.getElementById('refund-auto-total-amount').textContent = '¥' + ap.toFixed(2);
    document.getElementById('refund-auto-consumed-lessons').textContent = cl;
    document.getElementById('refund-auto-consumed-amount').textContent = '¥' + (parseFloat(orderRow.consumed_amount || 0)).toFixed(2);
    document.getElementById('refund-auto-remaining-lessons').textContent = remainingLessons;
    document.getElementById('refund-auto-remaining-amount').textContent = '¥' + remainingAmount.toFixed(2);

    document.getElementById('refund-custom-deduction').value = '0';
    document.getElementById('refund-actual-amount-display').textContent = '¥' + remainingAmount.toFixed(2);
    document.getElementById('refund-bank-name').value = '';
    document.getElementById('refund-bank-account').value = '';
    document.getElementById('refund-account-holder').value = '';
    document.getElementById('refund-apply-reason').value = '';

    openModal('modal-refund-apply');
}

function calcActualRefund() {
    const deduction = parseFloat(document.getElementById('refund-custom-deduction').value) || 0;
    const actual = Math.max(0, refundApplyRemainingAmount - deduction);
    document.getElementById('refund-actual-amount-display').textContent = '¥' + actual.toFixed(2);
}

function onRefundMethodChange() {
    const method = document.querySelector('input[name="refund-method"]:checked')?.value || 'cash';
    const bankSection = document.getElementById('refund-bank-info-section');
    const hint = document.querySelector('#modal-refund-apply .refund-account-hint');
    if (method === 'account') {
        if (bankSection) bankSection.style.display = 'none';
        if (hint) hint.style.display = 'flex';
    } else {
        if (bankSection) bankSection.style.display = '';
        if (hint) hint.style.display = 'none';
    }
}

async function submitRefundApply() {
    const orderId = parseInt(document.getElementById('refund-apply-order-id').value) || 0;
    if (!orderId) { showToast('订单信息错误', 'error'); return; }
    const customDeduction = parseFloat(document.getElementById('refund-custom-deduction').value) || 0;
    const bankName = document.getElementById('refund-bank-name').value.trim();
    const bankAccount = document.getElementById('refund-bank-account').value.trim();
    const accountHolder = document.getElementById('refund-account-holder').value.trim();
    const refundReason = document.getElementById('refund-apply-reason').value.trim();

    // 读取退费方式
    const refundTo = document.querySelector('input[name="refund-method"]:checked')?.value || 'cash';

    // 退到银行卡时校验银行信息
    if (refundTo === 'cash') {
        if (!bankName) { showToast('请填写转账银行', 'error'); return; }
        if (!bankAccount) { showToast('请填写银行卡号', 'error'); return; }
        if (!accountHolder) { showToast('请填写开户人', 'error'); return; }
    }
    if (!refundReason) { showToast('请填写退费原因', 'error'); return; }

    try {
        const res = await fetch(API_BASE + 'submit_refund', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                order_id: orderId,
                custom_deduction: customDeduction,
                bank_name: bankName,
                bank_account: bankAccount,
                account_holder: accountHolder,
                refund_reason: refundReason,
                refund_to: refundTo
            })
        });
        const data = await res.json();
        if (data.error) { showToast(data.error, 'error'); return; }
        showToast('退费申请已提交');
        closeModal('modal-refund-apply');
        // 刷新学员课程列表
        loadStudentCourses(currentViewStudentId);
    } catch (e) {
        showToast('网络错误，请重试', 'error');
    }
}

function renderCashflowRankChart(data) {
    const wrapper = document.getElementById('cf-rank-chart-wrapper');
    const canvas = document.getElementById('cf-rank-chart');
    if (!wrapper || !canvas) return;

    const selectedList = (typeof getSelectedCashflowCampuses === 'function') ? getSelectedCashflowCampuses() : [];
    const totalCampus = document.querySelectorAll('.cf-campus-cb').length;
    const hasFilter = selectedList.length > 0 && selectedList.length < totalCampus;
    const rankings = data.rankings || [];

    // 筛选了校区时隐藏排名图
    if (hasFilter || rankings.length === 0) {
        wrapper.style.display = 'none';
        if (cfRankChart) { cfRankChart.destroy(); cfRankChart = null; }
        return;
    }

    wrapper.style.display = 'block';

    // 按净现金流降序排列（前端兜底排序，后端已排但防 Object.keys 等打乱顺序）
    const sorted = [...rankings].sort((a, b) => b.net - a.net);
    const labels = sorted.map(r => r.campus);
    const incomeData = sorted.map(r => r.income);
    const expenseData = sorted.map(r => r.expense);
    const netData = sorted.map(r => r.net);

    // 颜色：收入绿色，支出红色，净现金流蓝/橙
    if (cfRankChart) cfRankChart.destroy();

    cfRankChart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: '总收入',
                    data: incomeData,
                    backgroundColor: 'rgba(56,161,105,0.7)',
                    borderColor: 'rgba(56,161,105,1)',
                    borderWidth: 1,
                    barPercentage: 0.7,
                },
                {
                    label: '总支出',
                    data: expenseData,
                    backgroundColor: 'rgba(229,62,62,0.7)',
                    borderColor: 'rgba(229,62,62,1)',
                    borderWidth: 1,
                    barPercentage: 0.7,
                },
                {
                    label: '净现金流',
                    data: netData,
                    backgroundColor: netData.map(v => v >= 0 ? 'rgba(124,58,237,0.7)' : 'rgba(167,139,250,0.7)'),
                    borderColor: netData.map(v => v >= 0 ? 'rgba(124,58,237,1)' : 'rgba(167,139,250,1)'),
                    borderWidth: 1,
                    barPercentage: 0.7,
                }
            ]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    ticks: { callback: v => '¥' + (v / 10000).toFixed(1) + '万' }
                },
                y: {
                    ticks: { font: { size: 12 } }
                }
            },
            plugins: {
                legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } },
                tooltip: {
                    callbacks: {
                        label: ctx => ctx.dataset.label + '：¥' + ctx.raw.toLocaleString('zh-CN', { minimumFractionDigits: 2 })
                    }
                }
            },
            interaction: { mode: 'index' }
        }
    });
}

// ==================== 退费记录列表（工作记录面板） ====================
let refundCampusData = []; // 退费校区筛选数据 [{name, region}]

async function loadRefundRecords() {
    try {
        const keyword = document.getElementById('search-refund')?.value || '';
        const dateFrom = document.getElementById('filter-refund-date-from')?.value || '';
        const dateTo = document.getElementById('filter-refund-date-to')?.value || '';
        const status = document.getElementById('filter-refund-status')?.value || '';
        const project = document.getElementById('filter-refund-project')?.value || '';
        const regionSel = document.getElementById('filter-refund-region');
        const campusSel = document.getElementById('filter-refund-campus');
        const params = new URLSearchParams({ page: refundPage, page_size: 15, _: Date.now() });
        if (keyword) params.set('keyword', keyword);
        if (dateFrom) params.set('date_from', dateFrom);
        if (dateTo) params.set('date_to', dateTo);
        if (status) params.set('status', status);
        if (project) params.set('project', project);
        // 校区筛选：选区域时收集该区域下所有校区；选具体校区时传单个校区
        let campusValue = '';
        if (regionSel && campusSel) {
            const region = regionSel.value;
            const campus = campusSel.value;
            if (region && !campus) {
                // 仅选了区域：收集该区域下所有校区
                const regionCampuses = refundCampusData.filter(c => c.region === region).map(c => c.name);
                if (regionCampuses.length > 0) campusValue = regionCampuses.join(',');
            } else if (campus) {
                campusValue = campus;
            }
        }
        if (campusValue) params.set('campus', campusValue);
        // 骨架屏加载态
        const tbody = document.querySelector('#table-refund-records tbody');
        if (tbody) {
            tbody.innerHTML = Array.from({length: 5}, () => `
                <tr class="skeleton-row">
                    <td><div class="skeleton-cell skeleton-sm"></div></td>
                    <td><div class="skeleton-cell skeleton-md"></div></td>
                    <td><div class="skeleton-cell skeleton-sm"></div></td>
                    <td><div class="skeleton-cell skeleton-lg"></div></td>
                    <td><div class="skeleton-cell skeleton-sm"></div></td>
                    <td><div class="skeleton-cell skeleton-md"></div></td>
                    <td><div class="skeleton-cell skeleton-md"></div></td>
                    <td><div class="skeleton-cell skeleton-md"></div></td>
                    <td><div class="skeleton-cell skeleton-sm"></div></td>
                    <td><div class="skeleton-cell skeleton-md"></div></td>
                    <td><div class="skeleton-cell skeleton-lg"></div></td>
                    <td><div class="skeleton-cell skeleton-sm"></div></td>
                </tr>
            `).join('');
        }
        const res = await fetch(API_BASE + 'list_refund_records&' + params);
        const data = await res.json();
        renderRefundRecordTable(data.data);
        renderPagination('pagination-refund', data.total, refundPage, 15, (p) => { refundPage = p; loadRefundRecords(); });
    } catch (e) {
        console.error('loadRefundRecords error:', e);
        const tbody = document.querySelector('#table-refund-records tbody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="12" style="text-align:center;color:#e74c3c;padding:30px;">加载失败：' + e.message + '</td></tr>';
    }
}

function renderRefundRecordTable(rows) {
    const tbody = document.querySelector('#table-refund-records tbody');
    if (!rows || rows.length === 0) {
        tbody.innerHTML = '<tr class="empty-row"><td colspan="12">暂无退费记录<span class="empty-subtitle">退费申请将在此处显示</span></td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(r => {
        const project = r.project || '课程';
        let projectBadge = '';
        if (project === '账户') {
            projectBadge = '<span class="refund-badge refund-badge-account">账户</span>';
        } else {
            projectBadge = '<span class="refund-badge refund-badge-course">课程</span>';
        }
        const isAccount = (project === '账户');
        const ar = parseFloat(r.actual_refund) || 0;
        const cd = parseFloat(r.custom_deduction) || 0;
        const subject = r.subject_level1 || '-';
        const refundMethod = r.refund_method || '转账';
        let methodBadge = '';
        if (refundMethod === '账户') {
            methodBadge = '<span class="refund-badge refund-badge-balance">账户</span>';
        } else {
            methodBadge = '<span class="refund-badge refund-badge-transfer">转账</span>';
        }
        const status = r.status || '';
        let statusHtml = '';
        if (status === '待审批') statusHtml = '<span class="tag tag-orange">待审批</span>';
        else if (status === '一级审批通过') statusHtml = '<span class="tag tag-blue">一级审批通过</span>';
        else if (status === '二级审批通过') statusHtml = '<span class="tag tag-blue">二级审批通过</span>';
        else if (status === '已退费') statusHtml = '<span class="tag tag-green">已退费</span>';
        else if (status === '审批驳回') statusHtml = '<span class="tag tag-red">审批驳回</span>';
        else statusHtml = status;
        // 操作按钮
        let btns = [];
        if (status === '待审批' || status === '一级审批通过' || status === '二级审批通过') {
            btns.push(`<button class="btn btn-primary btn-sm" onclick="showApproveModal(${r.id})">审批</button>`);
        } else {
            btns.push(`<button class="btn-link" onclick="showApproveModal(${r.id})" style="padding:5px 0;">查看详情</button>`);
        }
        // 撤销按钮：已退费或驳回不可撤销
        if (status !== '已退费' && status !== '审批驳回') {
            btns.push(`<button class="btn btn-warn btn-sm" onclick="cancelRefund(${r.id})">撤销</button>`);
        }
        let optHtml = btns.length > 0 ? `<div style="display:flex;gap:8px;align-items:center;flex-wrap:nowrap;">${btns.join('')}</div>` : '';
        const created = r.created_at ? r.created_at.slice(0, 16) : '';
        return `<tr>
            <td style="font-family:monospace;font-size:12px;">${esc(r.order_no || '')}</td>
            <td>${esc(r.student_name || '')}</td>
            <td>${projectBadge}</td>
            <td>${esc(r.content || r.course_name || '')}</td>
            <td>${esc(subject)}</td>
            <td>${esc(r.campus || '')}</td>
            <td style="font-weight:bold;color:#e74c3c;">¥${ar.toFixed(2)}</td>
            <td>${isAccount ? '¥0.00' : '¥'+cd.toFixed(2)}</td>
            <td>${methodBadge}</td>
            <td style="white-space:nowrap;">${statusHtml}</td>
            <td>${created}</td>
            <td>${optHtml}</td>
        </tr>`;
    }).join('');
}

// ==================== 退费记录区域/校区两级筛选 ====================
async function initRefundCampusFilter() {
    const regionSel = document.getElementById('filter-refund-region');
    const campusSel = document.getElementById('filter-refund-campus');
    if (!regionSel || !campusSel) return;
    if (regionSel.options.length > 1) return; // 已初始化过，跳过
    try {
        const res = await fetch(API_BASE + 'list_organizations');
        const data = await res.json();
        const orgs = (data.data && data.data.flat) || [];
        // 构建区域→校区映射
        const regionMap = {}; // regionName -> [{id, name}]
        refundCampusData = [];
        orgs.forEach(o => {
            if (o.type === '校区' && o.parent_id) {
                const parent = orgs.find(p => p.id == o.parent_id);
                if (parent) {
                    if (!regionMap[parent.name]) regionMap[parent.name] = [];
                    regionMap[parent.name].push({ id: o.id, name: o.name });
                    refundCampusData.push({ name: o.name, region: parent.name });
                }
            }
        });
        // 填充区域下拉
        Object.keys(regionMap).sort().forEach(rName => {
            const opt = document.createElement('option');
            opt.value = rName;
            opt.textContent = rName;
            regionSel.appendChild(opt);
        });
        // 填充校区下拉（初始显示全部）
        orgs.filter(o => o.type === '校区').forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.name;
            opt.textContent = c.name;
            campusSel.appendChild(opt);
        });
    } catch (e) {
        console.error('initRefundCampusFilter error:', e);
    }
}

function onRefundRegionChange() {
    const regionSel = document.getElementById('filter-refund-region');
    const campusSel = document.getElementById('filter-refund-campus');
    if (!regionSel || !campusSel) return;
    const region = regionSel.value;
    // 重建校区下拉选项
    campusSel.innerHTML = '<option value="">全部校区</option>';
    refundCampusData
        .filter(c => !region || c.region === region)
        .forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.name;
            opt.textContent = c.name;
            campusSel.appendChild(opt);
        });
    loadRefundRecords();
}

// ==================== 退费审批弹窗 ====================
let currentApproveId = null;

async function showApproveModal(id) {
    currentApproveId = id;
    openModal('modal-refund-approve');
    document.getElementById('refund-approve-content').innerHTML = '<div style="text-align:center;color:#999;padding:40px;">加载中...</div>';
    document.getElementById('refund-approve-footer').style.display = 'none';
    try {
        const res = await fetch(API_BASE + 'get_refund_record&id=' + id);
        const data = await res.json();
        if (data.error) { showToast(data.error, 'error'); closeModal('modal-refund-approve'); return; }
        const rr = data.data;
        const status = rr.status || '';
        const project = rr.project || '课程';
        const isAccount = (project === '账户');
        const rrMethod = rr.refund_method || '';
        const rrProject = rr.project || '课程';
        const canApprove = (status === '待审批' || status === '一级审批通过' || status === '二级审批通过');
        // 审批进度
        const steps = ['一级审批', '二级审批', '财务确认'];
        let currentStepIdx = 0;
        if (rr.approval_stage === '二级审批') currentStepIdx = 1;
        else if (rr.approval_stage === '财务确认') currentStepIdx = 2;
        else if (status === '已退费') currentStepIdx = 3;
        else if (status === '审批驳回') currentStepIdx = -1;

        // === 进度条（含 line-done 连线变色） ===
        let stepsHtml = '<div class="approval-steps">';
        steps.forEach((s, i) => {
            let cls = 'approval-step';
            if (status === '审批驳回') cls += ' approval-step-rejected';
            else if (i < currentStepIdx || status === '已退费') cls += ' approval-step-done';
            else if (i === currentStepIdx) cls += ' approval-step-current';
            stepsHtml += `<div class="${cls}"><div class="approval-step-dot"></div><span>${s}</span></div>`;
            if (i < 2) {
                let lineCls = 'approval-step-line';
                if (status !== '审批驳回' && i < currentStepIdx) lineCls += ' line-done';
                stepsHtml += `<div class="${lineCls}"></div>`;
            }
        });
        stepsHtml += '</div>';

        // === 驳回横幅（CSS 类化） ===
        if (status === '审批驳回') {
            stepsHtml += `<div class="approve-reject-banner"><span class="banner-icon">⚠️</span>驳回原因：${esc(rr.reject_reason || '无')}</div>`;
        }

        // === 已审批人 Pill 标签 ===
        let approverTagsHtml = '';
        if (rr.approver1) approverTagsHtml += `<span class="approve-approver-tag">${esc(rr.approver1)}</span>`;
        if (rr.approver2) approverTagsHtml += `<span class="approve-approver-tag">${esc(rr.approver2)}</span>`;
        if (rr.approver3) approverTagsHtml += `<span class="approve-approver-tag">${esc(rr.approver3)}</span>`;
        if (approverTagsHtml) approverTagsHtml = `<div class="approve-approver-tags">${approverTagsHtml}</div>`;

        // === 信息卡片 HTML（三卡片布局） ===
        let infoCardsHtml = `<div class="approve-info-cards">
            <div class="approve-info-card">
                <div class="card-title">📋 学员信息</div>
                <div class="info-row"><span class="info-label">学员</span><span class="info-value">${esc(rr.student_name || '')}</span></div>
                <div class="info-row"><span class="info-label">校区</span><span class="info-value">${esc(rr.campus || '-')}</span></div>
                <div class="info-row"><span class="info-label">课程</span><span class="info-value">${esc(rr.course_name || '')}</span></div>
            </div>
            <div class="approve-info-card">
                <div class="card-title">📦 订单信息</div>
                <div class="info-row"><span class="info-label">订单号</span><span class="info-value mono">${esc(rr.order_no || '')}</span></div>
                <div class="info-row"><span class="info-label">退费类型</span><span class="info-value">${isAccount ? '账户余额退费' : '课程退费'}</span></div>
                <div class="info-row"><span class="info-label">退费原因</span><span class="info-value">${esc(rr.refund_reason || '-')}</span></div>`;
        if (!isAccount && rrMethod !== '账户') {
            infoCardsHtml += `
                <div class="info-row"><span class="info-label">转账银行</span><span class="info-value">${esc(rr.bank_name || '-')}</span></div>
                <div class="info-row"><span class="info-label">银行卡号</span><span class="info-value mono">${esc(rr.bank_account || '-')}</span></div>
                <div class="info-row"><span class="info-label">开户人</span><span class="info-value">${esc(rr.account_holder || '-')}</span></div>`;
        }
        infoCardsHtml += `</div>
            <div class="approve-info-card card-amount card-full">
                <div class="card-title">💰 金额明细</div>
                <div class="amount-grid">
                    <div class="amount-item"><div class="amount-label">报读课时</div><div class="amount-value">${parseInt(rr.total_lessons) || 0}</div></div>
                    <div class="amount-item"><div class="amount-label">报读金额</div><div class="amount-value">¥${Number(rr.total_amount || 0).toFixed(2)}</div></div>
                    <div class="amount-item"><div class="amount-label">消耗课时</div><div class="amount-value">${parseInt(rr.consumed_lessons) || 0}</div></div>
                    <div class="amount-item"><div class="amount-label">消耗金额</div><div class="amount-value">¥${Number(rr.consumed_amount || 0).toFixed(2)}</div></div>
                    <div class="amount-item"><div class="amount-label">剩余可退课时</div><div class="amount-value">${parseInt(rr.remaining_lessons) || 0}</div></div>
                    <div class="amount-item"><div class="amount-label">剩余可退金额</div><div class="amount-value">¥${Number(rr.remaining_amount || 0).toFixed(2)}</div></div>
                    <div class="amount-item"><div class="amount-label">自定义扣减</div><div class="amount-value"><input type="number" id="approve-custom-deduction" value="${Number(rr.custom_deduction || 0)}" step="0.01" min="0" oninput="onApproveDeductionChange()" style="width:100px;padding:4px 8px;border:1px solid #ddd;border-radius:6px;font-size:13px;text-align:right;"></div></div>
                    <div class="amount-item amount-hero"><div class="amount-label">实退金额</div><div class="amount-value" id="approve-actual-refund-display">¥${Number(rr.actual_refund || 0).toFixed(2)}</div></div>
                </div>
            </div>
        </div>`;

        // === 组装内容 ===
        document.getElementById('refund-approve-content').innerHTML = `
            ${infoCardsHtml}
            <div class="approve-section-title">审批进度</div>
            ${stepsHtml}
            ${approverTagsHtml}
            ${(rr.approval_stage === '二级审批' && rrProject === '课程' && rrMethod === '账户') ? '<div class="approve-account-hint"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg> 二级审批通过后自动进入学员账户余额</div>' : ''}
            ${canApprove ? `<div class="approve-action-area">
                <div class="approve-approver-row">
                    <label>审批人</label>
                    <input type="text" id="approve-approver-name" placeholder="请输入审批人姓名">
                </div>
                ${rr.approval_stage === '财务确认' ? `<div class="approve-refund-to-group">
                    <label>退款方式</label>
                    <div class="refund-to-options">
                        <label class="refund-to-radio-label"><input type="radio" name="approve-refund-to" value="cash" checked><span class="refund-to-radio-custom"></span>退到银行卡</label>
                        ${(isAccount || (rrProject==='课程' && rrMethod==='账户')) ? '' : `<label class="refund-to-radio-label"><input type="radio" name="approve-refund-to" value="balance"><span class="refund-to-radio-custom"></span>退到余额</label>`}
                    </div>
                </div>` : ''}
                <div id="approve-reject-reason-group" style="display:none;">
                    <label>驳回原因 <span style="color:red;">*</span></label>
                    <textarea id="approve-reject-reason" rows="2" placeholder="请输入驳回原因"></textarea>
                </div>
            </div>` : ''}
        `;
        // 初始化扣减金额计算
        approveRemainingAmount = Number(rr.remaining_amount || 0);
        if (canApprove) {
            document.getElementById('refund-approve-footer').style.display = 'flex';
            document.getElementById('btn-refund-reject').style.display = 'inline-block';
            document.getElementById('btn-refund-approve').style.display = 'inline-block';
        } else {
            document.getElementById('refund-approve-footer').style.display = 'flex';
            document.getElementById('btn-refund-reject').style.display = 'none';
            document.getElementById('btn-refund-approve').style.display = 'none';
        }
    } catch (e) {
        document.getElementById('refund-approve-content').innerHTML = '<div style="text-align:center;color:#e74c3c;padding:40px;">加载失败</div>';
    }
}

// 审批弹窗内自定义扣减调整
let approveRemainingAmount = 0;
function onApproveDeductionChange() {
    const deduction = parseFloat(document.getElementById('approve-custom-deduction')?.value) || 0;
    const actual = Math.max(0, approveRemainingAmount - deduction);
    const display = document.getElementById('approve-actual-refund-display');
    if (display) display.textContent = '¥' + actual.toFixed(2);
}

async function submitApproval(action) {
    const approver = document.getElementById('approve-approver-name')?.value.trim() || '';
    const rejectReason = document.getElementById('approve-reject-reason')?.value.trim() || '';
    if (action === 'reject') {
        if (!rejectReason) {
            const group = document.getElementById('approve-reject-reason-group');
            if (group) group.style.display = 'block';
            showToast('请输入驳回原因', 'error');
            return;
        }
    }
    try {
        let refundTo = 'cash';
        const refundToRadio = document.querySelector('input[name="approve-refund-to"]:checked');
        if (refundToRadio) refundTo = refundToRadio.value;
        // 读取调整后的扣减金额
        const customDeduction = parseFloat(document.getElementById('approve-custom-deduction')?.value) || 0;
        const actualRefund = Math.max(0, approveRemainingAmount - customDeduction);
        const body = {
            id: currentApproveId,
            action: action,
            approver: approver,
            reject_reason: rejectReason,
            custom_deduction: customDeduction,
            actual_refund: actualRefund
        };
        if (action === 'approve') body.refund_to = refundTo;
        const res = await fetch(API_BASE + 'approve_refund', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        const data = await res.json();
        if (data.error) { showToast(data.error, 'error'); return; }
        showToast(data.message || '操作成功');
        closeModal('modal-refund-approve');
        loadRefundRecords();
        if (currentViewStudentId) loadStudentCourses(currentViewStudentId);
    } catch (e) {
        showToast('网络错误，请重试', 'error');
    }
}

async function cancelRefund(id) {
    showCustomConfirm('确定要撤销该退费申请吗？', async () => {
    try {
        const res = await fetch(API_BASE + 'cancel_refund', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        const data = await res.json();
        if (data.error) { showToast(data.error, 'error'); return; }
        showToast(data.message || '撤销成功');
        loadRefundRecords();
        if (currentViewStudentId) loadStudentCourses(currentViewStudentId);
    } catch (e) {
        showToast('网络错误，请重试', 'error');
    }
    });
}

// ==================== 账户退费 ====================
let accountRefundMaxAmount = 0;

async function showAccountRefundModal() {
    const sid = currentViewStudentId;
    if (!sid) { showToast('请先选择学员', 'error'); return; }

    try {
        const res = await fetch(API_BASE + 'get_student_account&student_id=' + sid);
        const data = await res.json();
        const balance = Number(data.balance || 0);

        if (balance <= 0) {
            showToast('账户余额为0，无法申请退费', 'warn');
            return;
        }

        accountRefundMaxAmount = balance;
        // 填充账户信息
        document.getElementById('account-refund-balance').textContent = '¥' + balance.toLocaleString('zh-CN', {minimumFractionDigits: 2});
        document.getElementById('account-refund-total-deposit').textContent = '¥' + Number(data.total_deposit || 0).toLocaleString('zh-CN', {minimumFractionDigits: 2});
        document.getElementById('account-refund-total-consume').textContent = '¥' + Number(data.total_consume || 0).toLocaleString('zh-CN', {minimumFractionDigits: 2});
        document.getElementById('account-refund-total-refund').textContent = '¥' + Number(data.total_refund || 0).toLocaleString('zh-CN', {minimumFractionDigits: 2});
        // 清空表单
        document.getElementById('account-refund-amount').value = '';
        document.getElementById('account-refund-amount-hint').innerHTML = '';
        document.getElementById('account-refund-bank-name').value = '';
        document.getElementById('account-refund-bank-account').value = '';
        document.getElementById('account-refund-account-holder').value = '';
        document.getElementById('account-refund-reason').value = '';

        // 加载一级学科下拉
        await loadAccountRefundSubjects();

        openModal('modal-account-refund');
    } catch (e) {
        console.error('showAccountRefundModal error:', e);
        showToast('加载账户信息失败', 'error');
    }
}

async function loadAccountRefundSubjects() {
    const select = document.getElementById('account-refund-subject');
    if (!select) return;
    select.innerHTML = '<option value="">请选择学科</option>';
    try {
        const res = await fetch(API_BASE + 'list_subjects');
        const data = await res.json();
        if (data && data.tree) {
            data.tree.forEach(parent => {
                const opt = document.createElement('option');
                opt.value = parent.name;
                opt.textContent = parent.name;
                select.appendChild(opt);
            });
        }
    } catch (e) {
        console.error('loadAccountRefundSubjects error:', e);
    }
}

function validateAccountRefundAmount() {
    const input = document.getElementById('account-refund-amount');
    const hint = document.getElementById('account-refund-amount-hint');
    const val = parseFloat(input.value);
    if (isNaN(val) || val <= 0) {
        hint.innerHTML = '<span style="color:#999;">请输入有效的退费金额</span>';
        return;
    }
    if (val > accountRefundMaxAmount) {
        hint.innerHTML = '<span style="color:#e74c3c;">⚠ 退费金额不能超过账户余额（当前余额：¥' + accountRefundMaxAmount.toLocaleString('zh-CN', {minimumFractionDigits: 2}) + '）</span>';
        input.style.borderColor = '#e74c3c';
    } else {
        hint.innerHTML = '<span style="color:#27ae60;">✓ 退费金额有效，实退：¥' + val.toLocaleString('zh-CN', {minimumFractionDigits: 2}) + '</span>';
        input.style.borderColor = '#27ae60';
    }
}

async function submitAccountRefund() {
    const sid = currentViewStudentId;
    if (!sid) { showToast('学员ID无效', 'error'); return; }

    const amount = parseFloat(document.getElementById('account-refund-amount').value);
    if (isNaN(amount) || amount <= 0) { showToast('请输入有效的退费金额', 'error'); return; }
    if (amount > accountRefundMaxAmount) { showToast('退费金额不能超过账户余额', 'error'); return; }

    const bankName = document.getElementById('account-refund-bank-name').value.trim();
    const bankAccount = document.getElementById('account-refund-bank-account').value.trim();
    const accountHolder = document.getElementById('account-refund-account-holder').value.trim();
    const reason = document.getElementById('account-refund-reason').value.trim();
    const subjectLevel1 = document.getElementById('account-refund-subject').value;

    if (!subjectLevel1) { showToast('请选择一级学科', 'error'); return; }
    if (!bankName) { showToast('请输入转账银行', 'error'); return; }
    if (!bankAccount) { showToast('请输入银行卡号', 'error'); return; }
    if (!accountHolder) { showToast('请输入开户人姓名', 'error'); return; }

    try {
        const res = await fetch(API_BASE + 'submit_refund', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                refund_type: 'account',
                student_id: sid,
                refund_amount: amount,
                subject_level1: subjectLevel1,
                bank_name: bankName,
                bank_account: bankAccount,
                account_holder: accountHolder,
                refund_reason: reason
            })
        });
        const data = await res.json();
        if (data.error) { showToast(data.error, 'error'); return; }
        showToast(data.message || '账户退费申请提交成功');
        closeModal('modal-account-refund');
        if (currentViewStudentId) loadStudentAccount(currentViewStudentId);
    } catch (e) {
        console.error('submitAccountRefund error:', e);
        showToast('网络错误，请重试', 'error');
    }
}

// ==================== 现金流统计 ====================
let cfBarChart = null;
let cfLineChart = null;
let cfExpenseChart = null;
let cfRankChart = null;

function initCashflowDateRange() {
    const granularity = document.getElementById('cf-granularity')?.value || 'monthly';
    updateCashflowDateInputs(granularity);
    initCashflowCampusFilter();
}

function updateCashflowDateInputs(granularity) {
    const elFrom = document.getElementById('cf-date-from');
    const elTo = document.getElementById('cf-date-to');
    if (!elFrom || !elTo) return;

    const now = new Date();
    const toYear = now.getFullYear();
    const toMonth = String(now.getMonth() + 1).padStart(2, '0');

    if (granularity === 'daily') {
        elFrom.type = 'date';
        elTo.type = 'date';
        const from3m = new Date(now);
        from3m.setMonth(from3m.getMonth() - 3);
        const fy = from3m.getFullYear();
        const fm = String(from3m.getMonth() + 1).padStart(2, '0');
        const fd = String(from3m.getDate()).padStart(2, '0');
        const td = String(now.getDate()).padStart(2, '0');
        elFrom.value = fy + '-' + fm + '-' + fd;
        elTo.value = toYear + '-' + toMonth + '-' + td;
    } else {
        elFrom.type = 'month';
        elTo.type = 'month';
        const from = new Date(now.getFullYear(), now.getMonth() - 2, 1);
        const fromYear = from.getFullYear();
        const fromMonth = String(from.getMonth() + 1).padStart(2, '0');
        elFrom.value = fromYear + '-' + fromMonth;
        elTo.value = toYear + '-' + toMonth;
    }
}

function onCashflowGranularityChange() {
    updateCashflowDateInputs(document.getElementById('cf-granularity').value);
    loadCashflow();
}

async function initOrderCampusFilter() {
    const regionSel = document.getElementById('filter-order-region');
    const campusSel = document.getElementById('filter-order-campus');
    if (!regionSel || !campusSel) return;
    if (regionSel.options.length > 1) return; // 已初始化过，跳过
    try {
        const res = await fetch(API_BASE + 'list_organizations');
        const data = await res.json();
        const orgs = (data.data && data.data.flat) || [];
        // 构建区域→校区映射，并存储校区数据
        const regionMap = {}; // regionName -> [{id, name}]
        orderCampusData = [];
        orgs.forEach(o => {
            if (o.type === '校区' && o.parent_id) {
                const parent = orgs.find(p => p.id == o.parent_id);
                if (parent) {
                    if (!regionMap[parent.name]) regionMap[parent.name] = [];
                    regionMap[parent.name].push({ id: o.id, name: o.name });
                    orderCampusData.push({ name: o.name, region: parent.name });
                }
            }
        });
        // 填充区域下拉
        Object.keys(regionMap).sort().forEach(rName => {
            const opt = document.createElement('option');
            opt.value = rName;
            opt.textContent = rName;
            regionSel.appendChild(opt);
        });
        // 填充校区下拉（初始显示全部）
        orgs.filter(o => o.type === '校区').forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.name;
            opt.textContent = c.name;
            campusSel.appendChild(opt);
        });
    } catch (e) {
        console.error('initOrderCampusFilter error:', e);
    }
}

function onOrderRegionChange() {
    const regionSel = document.getElementById('filter-order-region');
    const campusSel = document.getElementById('filter-order-campus');
    if (!regionSel || !campusSel) return;
    const region = regionSel.value;
    // 重建校区下拉选项（display:none 对 option 元素无效）
    campusSel.innerHTML = '<option value="">全部校区</option>';
    orderCampusData
        .filter(c => !region || c.region === region)
        .forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.name;
            opt.textContent = c.name;
            campusSel.appendChild(opt);
        });
    loadOrders();
}

async function initCashflowCampusFilter() {
    try {
        const res = await fetch(API_BASE + 'list_organizations');
        const data = await res.json();
        const orgs = (data.data && data.data.flat) || [];
        // Build tree: regions (type=部门) as parents, campuses (type=校区) as children
        const regionMap = {};
        const orphanCampuses = [];
        orgs.forEach(o => {
            if (o.type === '部门') {
                if (!regionMap[o.id]) regionMap[o.id] = { name: o.name, campuses: [] };
            } else if (o.type === '校区') {
                if (o.parent_id && regionMap[o.parent_id]) {
                    regionMap[o.parent_id].campuses.push(o.name);
                } else {
                    orphanCampuses.push(o.name);
                }
            }
        });
        // Build tree data
        window._cfCampusTree = [];
        const regionList = Object.values(regionMap).filter(r => r.campuses.length > 0);
        regionList.forEach(r => {
            window._cfCampusTree.push({ type: 'region', name: r.name, children: r.campuses });
        });
        if (orphanCampuses.length > 0) {
            window._cfCampusTree.push({ type: 'region', name: '其他校区', children: orphanCampuses });
        }
        renderCampusTree();
    } catch (e) {
        console.error('initCashflowCampusFilter error:', e);
    }
}

function renderCampusTree() {
    const tree = document.getElementById('cf-campus-tree');
    if (!tree) return;
    const data = window._cfCampusTree || [];
    let html = '';
    data.forEach((region, ri) => {
        html += '<div class="cf-tree-region">';
        html += '<label class="cf-tree-check cf-tree-parent"><input type="checkbox" class="cf-region-cb" data-ri="' + ri + '" onchange="toggleRegion(' + ri + ')"> ' + escHtml(region.name) + '</label>';
        html += '<div class="cf-tree-children">';
        region.children.forEach((campus, ci) => {
            html += '<label class="cf-tree-check cf-tree-child"><input type="checkbox" class="cf-campus-cb" data-ri="' + ri + '" data-ci="' + ci + '" onchange="toggleCampus(' + ri + ',' + ci + ')"> ' + escHtml(campus) + '</label>';
        });
        html += '</div></div>';
    });
    tree.innerHTML = html;
}

function toggleCampusTree() {
    const dd = document.getElementById('cf-campus-dropdown');
    if (dd) dd.style.display = dd.style.display === 'none' ? 'block' : 'none';
}

function toggleRegion(ri) {
    const cb = document.querySelector('.cf-region-cb[data-ri="' + ri + '"]');
    if (!cb) return;
    const checked = cb.checked;
    document.querySelectorAll('.cf-campus-cb[data-ri="' + ri + '"]').forEach(c => { c.checked = checked; });
    updateCampusText();
    updateAllCheckbox();
}

function toggleCampus(ri, ci) {
    updateRegionCheckbox(ri);
    updateCampusText();
    updateAllCheckbox();
}

function updateRegionCheckbox(ri) {
    const children = document.querySelectorAll('.cf-campus-cb[data-ri="' + ri + '"]');
    const regionCb = document.querySelector('.cf-region-cb[data-ri="' + ri + '"]');
    if (!regionCb || children.length === 0) return;
    const allChecked = Array.from(children).every(c => c.checked);
    const noneChecked = Array.from(children).every(c => !c.checked);
    regionCb.checked = allChecked;
    regionCb.indeterminate = !allChecked && !noneChecked;
}

function toggleAllCashflowCampuses() {
    const allCb = document.getElementById('cf-campus-all');
    if (!allCb) return;
    document.querySelectorAll('.cf-region-cb').forEach(c => { c.checked = checked; c.indeterminate = false; });
    document.querySelectorAll('.cf-campus-cb').forEach(c => { c.checked = checked; });
    updateCampusText();
}

function updateAllCheckbox() {
    const allCampus = document.querySelectorAll('.cf-campus-cb');
    if (allCampus.length === 0) return;
    const allCb = document.getElementById('cf-campus-all');
    const allChecked = Array.from(allCampus).every(c => c.checked);
    const noneChecked = Array.from(allCampus).every(c => !c.checked);
    allCb.checked = allChecked;
    allCb.indeterminate = !allChecked && !noneChecked;
}

function updateCampusText() {
    const checked = document.querySelectorAll('.cf-campus-cb:checked');
    const textEl = document.getElementById('cf-campus-text');
    if (!textEl) return;
    const total = document.querySelectorAll('.cf-campus-cb').length;
    if (checked.length === 0 || checked.length === total) {
        textEl.textContent = '全部校区';
    } else if (checked.length <= 3) {
        const names = Array.from(checked).map(c => c.parentElement.textContent.trim());
        textEl.textContent = names.join(', ');
    } else {
        textEl.textContent = '已选 ' + checked.length + ' 个校区';
    }
}

function getSelectedCashflowCampuses() {
    const checked = document.querySelectorAll('.cf-campus-cb:checked');
    return Array.from(checked).map(c => c.parentElement.textContent.trim());
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const wrap = document.getElementById('cf-campus-wrap');
    const dd = document.getElementById('cf-campus-dropdown');
    if (wrap && dd && !wrap.contains(e.target)) {
        dd.style.display = 'none';
    }
});


async function loadCashflow() {
    try {
        const granularity = document.getElementById('cf-granularity')?.value || 'monthly';
        const campus = document.getElementById('cf-campus')?.value || '';
        const dateFrom = document.getElementById('cf-date-from')?.value || '';
        const dateTo = document.getElementById('cf-date-to')?.value || '';
        const params = new URLSearchParams({ granularity: granularity });
        // Multi-campus: collect from tree-select checkboxes
        const selectedCampuses = (typeof getSelectedCashflowCampuses === 'function') ? getSelectedCashflowCampuses() : [];
        const totalCampuses = document.querySelectorAll('.cf-campus-cb').length;
        if (selectedCampuses.length > 0 && selectedCampuses.length < totalCampuses) {
            params.set('campuses', selectedCampuses.join(','));
        }
        // Backward compat: single select fallback
        if (campus) params.set('campus', campus);
        if (dateFrom) params.set('date_from', dateFrom);
        if (dateTo) params.set('date_to', dateTo);
        const res = await fetch(API_BASE + 'get_cashflow_stats&' + params);
        const data = await res.json();
        renderCashflow(data);
        renderCashflowCharts(data, campus);
        renderCashflowRankChart(data);
    } catch (e) {
        console.error('loadCashflow error:', e);
        const tbody = document.querySelector('#table-cashflow tbody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#e74c3c;padding:30px;">加载失败：' + e.message + '</td></tr>';
    }
}

function renderCashflow(data) {
    const s = data.summary || {};
    const totalIncome = parseFloat(s.total_income) || 0;
    const totalExpense = parseFloat(s.total_expense) || 0;
    const netCashflow = parseFloat(s.net_cashflow) || 0;
    document.getElementById('cf-total-income').textContent = '¥' + totalIncome.toLocaleString('zh-CN', { minimumFractionDigits: 2 });
    document.getElementById('cf-total-expense').textContent = '¥' + totalExpense.toLocaleString('zh-CN', { minimumFractionDigits: 2 });
    const netEl = document.getElementById('cf-net-cashflow');
    netEl.textContent = (netCashflow >= 0 ? '¥' : '-¥') + Math.abs(netCashflow).toLocaleString('zh-CN', { minimumFractionDigits: 2 });
    netEl.style.color = netCashflow >= 0 ? 'var(--color-success)' : 'var(--color-danger)';

    const rows = data.data || [];
    const tbody = document.querySelector('#table-cashflow tbody');
    if (rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#999;padding:30px;">暂无数据</td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(r => {
        const incAmt = parseFloat(r.income_amount) || 0;
        const expAmt = parseFloat(r.expense_amount) || 0;
        const net = parseFloat(r.net) || 0;
        const netCls = net >= 0 ? 'color:var(--color-success);' : 'color:var(--color-danger);';
        return `<tr>
            <td>${esc(r.campus)}</td>
            <td>${esc(r.date)}</td>
            <td>${r.income_cnt}</td>
            <td>¥${incAmt.toLocaleString('zh-CN', { minimumFractionDigits: 2 })}</td>
            <td>${r.expense_cnt}</td>
            <td>¥${expAmt.toLocaleString('zh-CN', { minimumFractionDigits: 2 })}</td>
            <td style="font-weight:bold;${netCls}">${net >= 0 ? '¥' : '-¥'}${Math.abs(net).toLocaleString('zh-CN', { minimumFractionDigits: 2 })}</td>
        </tr>`;
    }).join('');
}

function renderCashflowCharts(data, campusFilter) {
    const rows = data.data || [];
    if (rows.length === 0) {
        if (cfBarChart) { cfBarChart.destroy(); cfBarChart = null; }
        if (cfLineChart) { cfLineChart.destroy(); cfLineChart = null; }
        if (cfExpenseChart) { cfExpenseChart.destroy(); cfExpenseChart = null; }
        return;
    }

    const isAllCampuses = !campusFilter;

    if (isAllCampuses) {
        // 全部校区模式：各校区数据按日期求和
        const dateAgg = {};
        const dateOrder = [];
        rows.forEach(r => {
            if (!dateAgg[r.date]) {
                dateAgg[r.date] = { income: 0, net: 0 };
                dateOrder.push(r.date);
            }
            dateAgg[r.date].income += parseFloat(r.income_amount) || 0;
            dateAgg[r.date].net += parseFloat(r.net) || 0;
        });
        // 按日期排序
        dateOrder.sort();
        const dates = dateOrder;
        const incomeData = dates.map(d => dateAgg[d].income);
        const netData = dates.map(d => dateAgg[d].net);

        const singleColor = 'rgba(124,58,237,0.8)';
        const singleBorder = 'rgba(124,58,237,1)';

        // 按订单类型堆叠柱状图
        const incomeByType = data.income_by_type || [];
        const typeColors = {
            '新报': 'rgba(56,161,105,0.85)',
            '续费': 'rgba(72,187,120,0.75)',
            '小课包': 'rgba(104,211,145,0.65)'
        };
        const typeDateMap = {};
        const allTypeDates = new Set();
        incomeByType.forEach(item => {
            if (!typeDateMap[item.order_type]) typeDateMap[item.order_type] = {};
            typeDateMap[item.order_type][item.date] = item.amount;
            allTypeDates.add(item.date);
        });
        const typeDates = Array.from(allTypeDates).sort();
        const orderTypes = ['新报', '续费', '小课包'];
        const typeDatasets = orderTypes.filter(t => typeDateMap[t]).map(t => ({
            label: t,
            data: typeDates.map(d => typeDateMap[t][d] || 0),
            backgroundColor: typeColors[t] || 'rgba(201,203,207,0.8)',
            borderColor: (typeColors[t] || 'rgba(201,203,207,0.8)').replace('0.8', '1'),
            borderWidth: 1
        }));

        const barCtx = document.getElementById('cf-bar-chart');
        if (barCtx) {
            if (cfBarChart) cfBarChart.destroy();
            cfBarChart = new Chart(barCtx, {
                type: 'bar',
                data: {
                    labels: typeDates,
                    datasets: typeDatasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { stacked: true, ticks: { maxRotation: 45, font: { size: 10 } } },
                        y: { stacked: true, ticks: { callback: v => '¥' + (v / 10000).toFixed(1) + '万' } }
                    },
                    plugins: {
                        legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } },
                        tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': ¥' + ctx.raw.toLocaleString('zh-CN', { minimumFractionDigits: 2 }) } }
                    },
                    interaction: { mode: 'index' }
                }
            });
        }

        // 柱状图
        const lineCtx = document.getElementById('cf-line-chart');
        if (lineCtx) {
            if (cfLineChart) cfLineChart.destroy();
            cfLineChart = new Chart(lineCtx, {
                type: 'bar',
                data: {
                    labels: dates,
                    datasets: [{
                        label: '全部校区',
                        data: netData,
                        backgroundColor: singleColor,
                        borderColor: singleBorder,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { ticks: { maxRotation: 45, font: { size: 10 } } },
                        y: { ticks: { callback: v => '¥' + (v / 10000).toFixed(1) + '万' } }
                    },
                    plugins: {
                        legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } },
                        tooltip: { callbacks: { label: ctx => '全部校区: ¥' + ctx.raw.toLocaleString('zh-CN', { minimumFractionDigits: 2 }) } }
                    },
                    interaction: { mode: 'index' }
                }
            });
        }

        // 总支出柱状图（全部校区模式）
        const expenseCtx = document.getElementById('cf-expense-chart');
        if (expenseCtx) {
            if (cfExpenseChart) cfExpenseChart.destroy();
            // expense = income - net
            const expenseData = dates.map((d, i) => {
                const exp = incomeData[i] - netData[i];
                return Math.max(0, exp);
            });
            cfExpenseChart = new Chart(expenseCtx, {
                type: 'bar',
                data: {
                    labels: dates,
                    datasets: [{
                        label: '全部校区',
                        data: expenseData,
                        backgroundColor: 'rgba(229,62,62,0.8)',
                        borderColor: 'rgba(229,62,62,1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { ticks: { maxRotation: 45, font: { size: 10 } } },
                        y: { ticks: { callback: v => '¥' + (v / 10000).toFixed(1) + '万' } }
                    },
                    plugins: {
                        legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } },
                        tooltip: { callbacks: { label: ctx => '全部校区: ¥' + ctx.raw.toLocaleString('zh-CN', { minimumFractionDigits: 2 }) } }
                    },
                    interaction: { mode: 'index' }
                }
            });
        }
        return;
    }

    // 单校区模式：保持原有行为
    const campusSet = new Set();
    const dateSet = new Set();
    rows.forEach(r => {
        campusSet.add(r.campus);
        dateSet.add(r.date);
    });
    const campuses = Array.from(campusSet);
    const dates = Array.from(dateSet);

    const lookup = {};
    rows.forEach(r => {
        if (!lookup[r.date]) lookup[r.date] = {};
        lookup[r.date][r.campus] = {
            income: parseFloat(r.income_amount) || 0,
            net: parseFloat(r.net) || 0
        };
    });

    const colors = [
        'rgba(124,58,237,0.85)', 'rgba(147,112,219,0.75)', 'rgba(167,139,250,0.65)',
        'rgba(139,92,246,0.8)', 'rgba(157,118,245,0.7)', 'rgba(109,40,217,0.9)',
        'rgba(181,158,252,0.6)', 'rgba(99,24,196,0.9)', 'rgba(196,181,253,0.55)',
        'rgba(157,133,245,0.7)'
    ];
    const borderColors = colors.map(c => c.replace('0.8', '1'));

    // 按订单类型堆叠柱状图
    const incomeByTypeSingle = data.income_by_type || [];
    const typeColorsSingle = {
        '新报': 'rgba(56,161,105,0.85)',
        '续费': 'rgba(72,187,120,0.75)',
        '小课包': 'rgba(104,211,145,0.65)'
    };
    const typeDateMapSingle = {};
    const allTypeDatesSingle = new Set();
    incomeByTypeSingle.forEach(item => {
        if (!typeDateMapSingle[item.order_type]) typeDateMapSingle[item.order_type] = {};
        typeDateMapSingle[item.order_type][item.date] = item.amount;
        allTypeDatesSingle.add(item.date);
    });
    const typeDatesSingle = Array.from(allTypeDatesSingle).sort();
    const orderTypesList = ['新报', '续费', '小课包'];
    const barDatasets = orderTypesList.filter(t => typeDateMapSingle[t]).map(t => ({
        label: t,
        data: typeDatesSingle.map(d => typeDateMapSingle[t][d] || 0),
        backgroundColor: typeColorsSingle[t] || 'rgba(201,203,207,0.8)',
        borderColor: (typeColorsSingle[t] || 'rgba(201,203,207,0.8)').replace('0.8', '1'),
        borderWidth: 1
    }));

    const barCtx = document.getElementById('cf-bar-chart');
    if (barCtx) {
        if (cfBarChart) cfBarChart.destroy();
        cfBarChart = new Chart(barCtx, {
            type: 'bar',
            data: { labels: typeDatesSingle, datasets: barDatasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { stacked: true, ticks: { maxRotation: 45, font: { size: 10 } } },
                    y: { stacked: true, ticks: { callback: v => '¥' + (v / 10000).toFixed(1) + '万' } }
                },
                plugins: {
                    legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } },
                    tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': ¥' + ctx.raw.toLocaleString('zh-CN', { minimumFractionDigits: 2 }) } }
                },
                interaction: { mode: 'index' }
            }
        });
    }

    const lineDatasets = campuses.map((campus, i) => ({
        label: campus,
        data: dates.map(d => (lookup[d] && lookup[d][campus] ? lookup[d][campus].net : null)),
        backgroundColor: colors[i % colors.length],
        borderColor: borderColors[i % colors.length],
        borderWidth: 2,
        pointRadius: 3,
        pointHoverRadius: 5,
        tension: 0.2,
        fill: false,
        spanGaps: false
    }));

    const lineCtx = document.getElementById('cf-line-chart');
    if (lineCtx) {
        if (cfLineChart) cfLineChart.destroy();
        cfLineChart = new Chart(lineCtx, {
            type: 'line',
            data: { labels: dates, datasets: lineDatasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { ticks: { maxRotation: 45, font: { size: 10 } } },
                    y: { ticks: { callback: v => '¥' + (v / 10000).toFixed(1) + '万' } }
                },
                plugins: {
                    legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } },
                    tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': ¥' + ctx.raw.toLocaleString('zh-CN', { minimumFractionDigits: 2 }) } }
                },
                interaction: { mode: 'index' }
            }
        });
    }

    // 总支出柱状图（单校区模式）
    const expenseCtx2 = document.getElementById('cf-expense-chart');
    if (expenseCtx2) {
        if (cfExpenseChart) cfExpenseChart.destroy();
        const expenseRedColors = ['rgba(229,62,62,0.85)', 'rgba(252,129,129,0.75)', 'rgba(254,178,178,0.65)', 'rgba(245,101,101,0.8)', 'rgba(220,38,38,0.9)', 'rgba(239,89,89,0.7)', 'rgba(210,20,20,0.9)', 'rgba(248,150,150,0.6)', 'rgba(200,10,10,0.85)', 'rgba(235,72,72,0.75)'];
        const expenseRedBorders = ['rgba(229,62,62,1)', 'rgba(252,129,129,1)', 'rgba(254,178,178,1)', 'rgba(245,101,101,1)', 'rgba(220,38,38,1)', 'rgba(239,89,89,1)', 'rgba(210,20,20,1)', 'rgba(248,150,150,1)', 'rgba(200,10,10,1)', 'rgba(235,72,72,1)'];
        const expenseByCampus = campuses.map((campus, i) => ({
            label: campus,
            data: dates.map(d => {
                const row = (lookup[d] && lookup[d][campus]) ? (lookup[d][campus].income - lookup[d][campus].net) : null;
                return row !== null ? Math.max(0, row) : null;
            }),
            backgroundColor: expenseRedColors[i % expenseRedColors.length],
            borderColor: expenseRedBorders[i % expenseRedColors.length],
            borderWidth: 1
        }));
        cfExpenseChart = new Chart(expenseCtx2, {
            type: 'bar',
            data: { labels: dates, datasets: expenseByCampus },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { ticks: { maxRotation: 45, font: { size: 10 } } },
                    y: { ticks: { callback: v => '¥' + (v / 10000).toFixed(1) + '万' } }
                },
                plugins: {
                    legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } },
                    tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': ¥' + (ctx.raw || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2 }) } }
                },
                interaction: { mode: 'index' }
            }
        });
    }
}

// ==================== 课表视图 ====================
let scheduleWeekOffset = 0;  // 当前周偏移量（0=本周）

function getMonday(offset) {
    const d = new Date();
    const day = d.getDay();
    const diff = day === 0 ? -6 : 1 - day;
    d.setDate(d.getDate() + diff + offset * 7);
    return d;
}

async function initScheduleCampusFilter() {
    const res = await api('list_organizations');
    const sel = document.getElementById('filter-schedule-campus');
    if (!sel) return;
    sel.innerHTML = '<option value="">全部校区</option>';
    const orgs = (res.data && res.data.flat) ? res.data.flat : [];
    window._campusMap = {};
    const campuses = orgs.filter(o => o.type === '校区');
    campuses.forEach(o => {
        const opt = document.createElement('option');
        opt.value = o.name;
        opt.textContent = o.name;
        sel.appendChild(opt);
        window._campusMap[o.name] = String(o.id);
    });
    if (campuses.length > 0) {
        sel.value = campuses[0].name;
    }
}

function initScheduleTeacherFilter() {
    // Teacher list comes from API response now; populated in loadScheduleView
}

function navigateWeek(dir) {
    scheduleWeekOffset += dir;
    loadScheduleView();
}

function navigateToday() {
    scheduleWeekOffset = 0;
    loadScheduleView();
}

let scheduleViewType = 'week';
function switchScheduleView(type) {
    scheduleViewType = type;
    scheduleWeekOffset = 0;
    document.getElementById('view-week-btn').style.background = type === 'week' ? '#1890ff' : '#fff';
    document.getElementById('view-week-btn').style.color = type === 'week' ? '#fff' : '#333';
    document.getElementById('view-month-btn').style.background = type === 'month' ? '#1890ff' : '#fff';
    document.getElementById('view-month-btn').style.color = type === 'month' ? '#fff' : '#333';
    document.getElementById('schedule-week-view').style.display = type === 'week' ? '' : 'none';
    document.getElementById('schedule-month-view').style.display = type === 'month' ? '' : 'none';
    loadScheduleView();
}

function loadScheduleView() {
    const campus = document.getElementById('filter-schedule-campus').value;
    const teacher = document.getElementById('filter-schedule-teacher') ? document.getElementById('filter-schedule-teacher').value : '';
    const classroom = document.getElementById('filter-schedule-classroom') ? document.getElementById('filter-schedule-classroom').value : '';

    let weekStart;
    if (scheduleViewType === 'month') {
        const now = new Date();
        now.setMonth(now.getMonth() + scheduleWeekOffset);
        weekStart = formatDate(new Date(now.getFullYear(), now.getMonth(), 1));
    } else {
        const monday = getMonday(scheduleWeekOffset);
        weekStart = formatDate(monday);
    }

    let url = 'get_schedule_view&view_type=' + scheduleViewType + '&week_start=' + weekStart;
    if (campus) url += '&campus=' + encodeURIComponent(campus);
    if (teacher) url += '&teacher=' + encodeURIComponent(teacher);
    if (classroom) url += '&classroom=' + encodeURIComponent(classroom);

    api(url, null, 'GET').then(res => {
        if (res.error) { showToast(res.error, 'error'); return; }
        const d = res.data;

        // Populate teacher & classroom filters from API response
        if (d.teachers && document.getElementById('filter-schedule-teacher')) {
            const tSel = document.getElementById('filter-schedule-teacher');
            if (tSel.dataset.loaded !== '1') {
                tSel.innerHTML = '<option value="">全部教师</option>';
                d.teachers.forEach(t => {
                    const opt = document.createElement('option');
                    opt.value = t; opt.textContent = t;
                    tSel.appendChild(opt);
                });
                tSel.dataset.loaded = '1';
            }
        }
        if (d.classrooms && document.getElementById('filter-schedule-classroom')) {
            const cSel = document.getElementById('filter-schedule-classroom');
            if (cSel.dataset.loaded !== '1') {
                cSel.innerHTML = '<option value="">全部教室</option>';
                d.classrooms.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c; opt.textContent = c;
                    cSel.appendChild(opt);
                });
                cSel.dataset.loaded = '1';
            }
        }

        document.getElementById('schedule-week-range').textContent = d.week_start + ' ~ ' + d.week_end;

        if (d.view_type === 'month') {
            renderMonthView(d);
        } else {
            renderWeekView(d);
        }
    }).catch(e => {
        console.error('加载课表失败:', e);
    });
}

// ===== 周视图渲染 =====
function renderWeekView(d) {
    const grid = d.grid;
    const allSlots = d.all_slots;
    const today = d.today;
    const conflictKeys = new Set(d.conflicts || []);
    const days = ['周一', '周二', '周三', '周四', '周五', '周六', '周日'];

    const colors = [
        { bg: '#EDF2FF', border: '#4A6CF7', text: '#2B4ACB' },  // Indigo
        { bg: '#E6FFFA', border: '#38B2AC', text: '#2C7A7B' },  // Teal
        { bg: '#FFF5F5', border: '#FC8181', text: '#C53030' },  // Red
        { bg: '#FFFAF0', border: '#ED8936', text: '#C05621' },  // Orange
        { bg: '#F0FFF4', border: '#68D391', text: '#2F855A' },  // Green
        { bg: '#FAF5FF', border: '#9F7AEA', text: '#6B46C1' },  // Purple
        { bg: '#EBF8FF', border: '#63B3ED', text: '#2B6CB0' },  // Blue
    ];
    const teacherColorMap = {};
    let colorIdx = 0;

    // Build thead
    let theadHTML = '<tr>';
    theadHTML += '<th width="90">时间段</th>';
    days.forEach((dayLabel, i) => {
        const date = grid[dayLabel].date;
        const isToday = date === today;
        const dateParts = date.split('-');
        theadHTML += '<th width="' + (i >= 5 ? '100' : '120') + '" class="' + (isToday ? 'schedule-today-col' : '') + '">' + dayLabel + '<br><small style="font-weight:400;color:#888;">' + (parseInt(dateParts[1]) + '/' + parseInt(dateParts[2])) + '</small></th>';
    });
    theadHTML += '</tr>';
    document.getElementById('schedule-thead').innerHTML = theadHTML;

    // Build tbody
    let tbodyHTML = '';
    if (allSlots.length === 0) {
        tbodyHTML = '<tr><td colspan="8" style="text-align:center;color:#999;padding:60px;">本周暂无排课数据</td></tr>';
    } else {
        allSlots.forEach(slotKey => {
            const [start, end] = slotKey.split('-');
            tbodyHTML += '<tr>';
            tbodyHTML += '<td class="schedule-time-cell">' + start + '<br>至<br>' + end + '</td>';
            days.forEach(dayLabel => {
                const cellSlots = (grid[dayLabel].slots || []).filter(s => s.start === start && s.end === end);
                const date = grid[dayLabel].date;
                const isToday = date === today;
                tbodyHTML += '<td class="' + (isToday ? 'schedule-today-col' : '') + '" data-day="' + dayLabel + '" data-start="' + start + '" data-end="' + end + '">';
                if (cellSlots.length > 0) {
                    cellSlots.forEach(s => {
                        if (!teacherColorMap[s.teacher]) {
                            teacherColorMap[s.teacher] = colors[colorIdx % colors.length];
                            colorIdx++;
                        }
                        const clr = teacherColorMap[s.teacher];
                        const conflictKey = dayLabel + '|' + s.classroom + '|' + s.start + '-' + s.end;
                        const isConflict = conflictKeys.has(conflictKey);
                        let infoStr = '';
                        if (s.teacher) infoStr += '<span class="schedule-card-teacher">' + escHtml(s.teacher) + '</span>';
                        if (s.class_name) infoStr += '<span class="schedule-card-name">' + escHtml(s.class_name) + '</span>';
                        if (s.classroom) infoStr += ' <span class="schedule-card-room">' + escHtml(s.classroom) + '</span>';
                        if (s.student_count > 0) infoStr += ' <span class="schedule-card-count">' + s.student_count + '人</span>';

                        tbodyHTML += '<div class="schedule-card' + (isConflict ? ' schedule-card-conflict' : '') + '" style="background:' + clr.bg + ';border-left:3px solid ' + clr.border + ';" onclick="showScheduleDetail(' + escAttr(JSON.stringify(s)) + ')">';
                        if (isConflict) tbodyHTML += '<span class="schedule-conflict-badge" title="教室冲突">' + '⚠️' + '</span>';
                        tbodyHTML += '<div class="schedule-card-info" style="line-height:1.5;">' + infoStr + '</div>';
                        tbodyHTML += '</div>';
                    });
                }
                tbodyHTML += '</td>';
            });
            tbodyHTML += '</tr>';
        });
    }
    document.getElementById('schedule-tbody').innerHTML = tbodyHTML;

    // 拖拽模式：给所有数据单元格绑定 drop 事件
    if (dragMode) {
        document.querySelectorAll('#schedule-tbody td[data-day]').forEach(td => {
            makeCellDropTarget(td, td.dataset.day, td.dataset.start, td.dataset.end);
        });
    }
}

// ===== 月视图渲染 =====
function renderMonthView(d) {
    const monthGrid = d.month_grid;
    const dateSlots = d.date_slots || {};
    const today = d.today;
    const conflictKeys = new Set(d.conflicts || []);

    const colors = [
        { bg: '#EDF2FF', border: '#4A6CF7', text: '#2B4ACB' },
        { bg: '#E6FFFA', border: '#38B2AC', text: '#2C7A7B' },
        { bg: '#FFF5F5', border: '#FC8181', text: '#C53030' },
        { bg: '#FFFAF0', border: '#ED8936', text: '#C05621' },
        { bg: '#F0FFF4', border: '#68D391', text: '#2F855A' },
        { bg: '#FAF5FF', border: '#9F7AEA', text: '#6B46C1' },
        { bg: '#EBF8FF', border: '#63B3ED', text: '#2B6CB0' },
    ];
    const teacherColorMap = {};
    let colorIdx = 0;

    let html = '';
    for (let i = 0; i < monthGrid.length; i++) {
        if (i % 7 === 0) html += '<div class="schedule-month-row">';
        const cell = monthGrid[i];
        if (cell === null) {
            html += '<div class="schedule-month-cell schedule-month-cell-empty"></div>';
        } else {
            const isToday = cell.date === today;
            const isWeekend = cell.dayOfWeek >= 6;
            const slots = dateSlots[cell.date] || [];
            html += '<div class="schedule-month-cell' + (isToday ? ' schedule-month-today' : '') + (isWeekend ? ' schedule-month-weekend' : '') + '">';
            html += '<div class="schedule-month-day">' + cell.day + '</div>';
            if (slots.length > 0) {
                slots.sort((a, b) => (a.start || '').localeCompare(b.start || ''));
                slots.forEach(s => {
                    if (!teacherColorMap[s.teacher]) {
                        teacherColorMap[s.teacher] = colors[colorIdx % colors.length];
                        colorIdx++;
                    }
                    const clr = teacherColorMap[s.teacher];
                    const conflictKey = cell.date + '|' + s.classroom + '|' + s.start + '-' + s.end;
                    const isConflict = conflictKeys.has(conflictKey);
                    html += '<div class="schedule-month-item' + (isConflict ? ' schedule-card-conflict' : '') + '" onclick="showScheduleDetail(' + escAttr(JSON.stringify(s)) + ')" title="' + escHtml((s.teacher||'') + ' ' + s.course_name + ' ' + s.start + '-' + s.end) + '" style="background:' + clr.bg + ';border-left:3px solid ' + clr.border + ';color:' + clr.text + ';">';
                    if (isConflict) html += '⚠️';
                    html += '<b>' + escHtml((s.teacher || '?').substring(0, 4)) + '</b> ';
                    html += escHtml(s.start) + ' ';
                    html += '<span style="font-size:9px;">' + escHtml((s.class_name || '').substring(0, 6)) + '</span>';
                    html += '</div>';
                });
            }
            html += '</div>';
        }
        if (i % 7 === 6) html += '</div>';
    }
    document.getElementById('schedule-month-grid').innerHTML = html;
}

// ===== 排课详情弹窗（增强版） =====
function takeAttendanceFromSchedule(scheduleId, classId, className, campus, courseName, teacher, classroom) {
    document.querySelectorAll('.modal-overlay').forEach(m => m.remove());
    switchPanel('panel-classes');
    showToast('请在班级列表中点击「考勤」进行操作', 'info');
}

function deleteScheduleEntry(scheduleId, className) {
    showCustomConfirm('确定删除课次「' + className + '」吗？此操作不可恢复。', function() {
        api('delete_schedule', { id: scheduleId }, 'POST').then(function(res) {
            if (res.error) { showToast(res.error, 'error'); return; }
            document.querySelectorAll('.modal-overlay').forEach(m => m.remove());
            showToast('课次已删除', 'success');
            loadScheduleView();
        });
    });
}

function showScheduleDetail(s) {
    let html = '<div style="line-height:2;">';
    html += '<p><strong>班级：</strong>' + escHtml(s.class_name) + '</p>';
    html += '<p><strong>老师：</strong>' + escHtml(s.teacher || '未设置') + '</p>';
    html += '<p><strong>教室：</strong>' + escHtml(s.classroom || '未设置') + '</p>';
    html += '<p><strong>课程：</strong>' + escHtml(s.course_name) + '</p>';
    html += '<p><strong>校区：</strong>' + escHtml(s.campus || '未设置') + '</p>';
    html += '<p><strong>时间段：</strong>' + s.start + ' - ' + s.end + '</p>';
    html += '</div>';

    let actions = '<div style="display:flex;gap:8px;margin-top:16px;">';
    if (s.class_id) {
        actions += '<button class="btn btn-sm" onclick="this.closest(\'.modal-overlay\').remove(); switchPanel(\'panel-classes\');" style="padding:6px 12px;">查看班级</button>';
        actions += '<button class="btn btn-sm" onclick="takeAttendanceFromSchedule(' + s.schedule_id + ',' + s.class_id + ',\'' + escAttr(s.class_name) + '\',\'' + escAttr(s.campus || '') + '\',\'' + escAttr(s.course_name) + '\',\'' + escAttr(s.teacher || '') + '\',\'' + escAttr(s.classroom || '') + '\');" style="padding:6px 12px;background:#1890ff;color:#fff;border:none;border-radius:4px;">📋 考勤</button>';
    }
    actions += '<button class="btn btn-sm btn-danger" onclick="deleteScheduleEntry(' + s.schedule_id + ',\'' + escAttr(s.class_name) + '\');" style="padding:6px 12px;">删除课次</button>';
    actions += '<button class="btn btn-primary btn-sm" onclick="this.closest(\'.modal-overlay\').remove()" style="padding:6px 14px;margin-left:auto;">关闭</button>';
    actions += '</div>';

    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.4);z-index:9999;display:flex;align-items:center;justify-content:center;';
    overlay.innerHTML = '<div class="modal-content" style="background:#fff;border-radius:8px;padding:24px;max-width:420px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,0.2);">'
        + '<h3 style="margin:0 0 16px;">排课详情</h3>'
        + html
        + actions
        + '</div>';
    document.body.appendChild(overlay);
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) overlay.remove();
    });
}

// ===== 键盘导航 =====
document.addEventListener('keydown', function(e) {
    // 课表键盘导航：考勤面板可见且课表标签激活时才生效
    const attPanel = document.getElementById('panel-attendance');
    const scheduleTab = document.getElementById('tab-schedule-view');
    if (!attPanel || attPanel.style.display === 'none') return;
    if (!scheduleTab || !scheduleTab.classList.contains('active')) return;
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT' || e.target.tagName === 'TEXTAREA') return;
    if (e.key === 'ArrowLeft') { e.preventDefault(); navigateWeek(-1); }
    if (e.key === 'ArrowRight') { e.preventDefault(); navigateWeek(1); }
    if (e.key === 't' || e.key === 'T') { e.preventDefault(); navigateToday(); }
});

function escHtml(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
function escAttr(s) { return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

// ===== 拖拽排课 =====
let dragMode = false;
let pendingCells = {}; // key: "周一|10:40-10:41" -> { course, teacher, classroom, course_id, course_campus }

function initDragResources() {
    const campus = document.getElementById('filter-schedule-campus').value;
    const campusId = campus ? (window._campusMap && window._campusMap[campus] ? window._campusMap[campus] : null) : null;
    // 课程：全量加载后按 campus_permission（ID 逗号分隔）前端过滤
    api('list_courses').then(res => {
        const list = document.getElementById('drag-course-list');
        if (!list) return;
        const courses = (res.data || []).filter(c => {
            if (!campus) return true;                       // 无校区筛选 → 全显示
            if (!c.campus_permission) return false;          // 未设置适用校区 → 不显示
            return c.campus_permission.split(',').includes(campusId);  // ID 匹配
        });
        list.innerHTML = courses.length ? courses.map(c =>
            '<div class="drag-item" draggable="true" data-type="course" data-id="' + c.id + '" data-name="' + escHtml(c.name) + '" data-campus="' + escHtml(c.campus_permission || '') + '" ondragstart="onDragStart(event)" ondragend="onDragEnd(event)">' + escHtml(c.name) + '</div>'
        ).join('') : '<div class="resource-empty">该校区暂无可用课程</div>';
    });
    // 教师：按部门（校区）筛选
    api('get_teachers').then(res => {
        const list = document.getElementById('drag-teacher-list');
        if (!list) return;
        const teachers = (res.teachers || []).filter(t => !campus || t.department === campus);
        list.innerHTML = teachers.length ? teachers.map(t =>
            '<div class="drag-item" draggable="true" data-type="teacher" data-name="' + escHtml(t.name) + '" ondragstart="onDragStart(event)" ondragend="onDragEnd(event)">' + escHtml(t.name) + (t.department ? ' <small style="color:#999;font-size:10px;">' + escHtml(t.department) + '</small>' : '') + '</div>'
        ).join('') : '<div class="resource-empty">暂无教师</div>';
    });
    // 教室：按校区筛选
    let crUrl = 'list_classrooms';
    api(crUrl).then(res => {
        const list = document.getElementById('drag-classroom-list');
        if (!list) return;
        const classrooms = (res.data || []).filter(cr => !campus || !cr.campus || cr.campus.indexOf(campus) !== -1);
        list.innerHTML = classrooms.length ? classrooms.map(cr =>
            '<div class="drag-item" draggable="true" data-type="classroom" data-name="' + escHtml(cr.name) + '" ondragstart="onDragStart(event)" ondragend="onDragEnd(event)">' + escHtml(cr.name) + '</div>'
        ).join('') : '<div class="resource-empty">该校区暂无可用教室</div>';
    });
}

function toggleDragMode() {
    const campus = document.getElementById('filter-schedule-campus').value;
    if (!dragMode && !campus) {
        showToast('请先选择校区，再进入排课模式', 'warn');
        return;
    }
    dragMode = !dragMode;
    const panel = document.getElementById('schedule-resource-panel');
    const toggle = document.getElementById('schedule-drag-toggle');
    if (dragMode) {
        panel.classList.add('schedule-resource-panel--open');
        toggle.innerHTML = '✅ 完成排课';
        toggle.style.background = '#52c41a'; toggle.style.color = '#fff'; toggle.style.borderColor = '#52c41a';
        initDragResources();
    } else {
        panel.classList.remove('schedule-resource-panel--open');
        toggle.innerHTML = '📋 排课模式';
        toggle.style.background = '#f0f0f0'; toggle.style.color = '#333'; toggle.style.borderColor = '#ddd';
        pendingCells = {};
    }
    loadScheduleView();
}

function onDragStart(e) {
    if (!dragMode) { e.preventDefault(); return; }
    const item = e.target.closest('.drag-item');
    if (!item) return;
    e.dataTransfer.setData('text/plain', JSON.stringify({
        type: item.dataset.type, id: item.dataset.id || '', name: item.dataset.name, campus: item.dataset.campus || ''
    }));
    e.dataTransfer.effectAllowed = 'copy';
    item.classList.add('drag-item--dragging');
}

function onDragEnd(e) {
    const item = e.target.closest('.drag-item');
    if (item) item.classList.remove('drag-item--dragging');
    document.querySelectorAll('.schedule-drop-cell--over').forEach(c => c.classList.remove('schedule-drop-cell--over'));
}

function makeCellDropTarget(td, dayLabel, start, end) {
    if (!dragMode) return;
    td.classList.add('schedule-drop-cell');
    const id = dayLabel + '|' + start + '-' + end;
    td.dataset.dropId = id;
    td.addEventListener('dragover', function(e) { e.preventDefault(); e.dataTransfer.dropEffect = 'copy'; td.classList.add('schedule-drop-cell--over'); });
    td.addEventListener('dragleave', function() { td.classList.remove('schedule-drop-cell--over'); });
    td.addEventListener('drop', function(e) {
        e.preventDefault(); td.classList.remove('schedule-drop-cell--over');
        const raw = e.dataTransfer.getData('text/plain');
        if (!raw) return;
        let data; try { data = JSON.parse(raw); } catch(_) { return; }
        if (!data.type || !data.name) return;
        if (!pendingCells[id]) pendingCells[id] = {};
        pendingCells[id][data.type] = data.name;
        if (data.type === 'course') { pendingCells[id].course_id = data.id; pendingCells[id].course_campus = data.campus; }
        updatePendingCellUI(td, id);
    });
}

function updatePendingCellUI(td, id) {
    const p = pendingCells[id] || {};
    const parts = [p.course ? '📘' + p.course : '', p.teacher ? '👨‍🏫' + p.teacher : '', p.classroom ? '🏫' + p.classroom : ''].filter(Boolean);
    let el = td.querySelector('.schedule-drop-pending');
    if (parts.length === 0) { if (el) el.remove(); return; }
    if (!el) { el = document.createElement('div'); el.className = 'schedule-drop-pending'; td.appendChild(el); }
    el.innerHTML = parts.join('<br>');
    if (p.course && p.teacher && p.classroom && !el.querySelector('.schedule-drop-confirm')) {
        const btn = document.createElement('button');
        btn.className = 'schedule-drop-confirm';
        btn.textContent = '✓ 创建';
        btn.onclick = function(e) { e.stopPropagation(); showScheduleCreateModal(id, td); };
        el.appendChild(btn);
    }
}

function showScheduleCreateModal(cellId, td) {
    const p = pendingCells[cellId];
    if (!p || !p.course || !p.teacher || !p.classroom) return;
    const parts = cellId.split('|'), dayLabel = parts[0], timeSlot = parts.slice(1).join('|');
    const [timeStart, timeEnd] = timeSlot.split('-');
    const dayMap = {'周一':1,'周二':2,'周三':3,'周四':4,'周五':5,'周六':6,'周日':7};
    const dayOfWeek = dayMap[dayLabel] || 1;
    document.getElementById('schedule-create-modal').style.display = 'flex';
    document.getElementById('schedule-create-summary').innerHTML =
        '<strong>课程：</strong>' + escHtml(p.course) + '<br>' +
        '<strong>教师：</strong>' + escHtml(p.teacher) + '<br>' +
        '<strong>教室：</strong>' + escHtml(p.classroom) + '<br>' +
        '<strong>时间：</strong>' + dayLabel + ' ' + timeStart + '-' + timeEnd;
    document.getElementById('sc-class-name').value = p.course + '班';
    const campus = document.getElementById('filter-schedule-campus').value || p.course_campus || '';
    var today = new Date(); today.setHours(0,0,0,0);
    // 计算拖动单元格的实际日期
    var monday = getMonday(scheduleWeekOffset);
    var cellDate = new Date(monday);
    cellDate.setDate(monday.getDate() + (dayOfWeek - 1));
    cellDate.setHours(0,0,0,0);
    // 如果单元格日期 < 今天 → 下周；>= 今天 → 本周
    if (cellDate < today) {
        cellDate.setDate(cellDate.getDate() + 7);
    }
    document.getElementById('sc-start-date').value = formatDate(cellDate);
    var endDate = new Date(cellDate); endDate.setMonth(endDate.getMonth() + 6);
    document.getElementById('sc-end-date').value = formatDate(endDate);
    window._scPending = { cellId, dayLabel, dayOfWeek, timeStart, timeEnd, campus, courseId: p.course_id, courseName: p.course, teacher: p.teacher, classroom: p.classroom };
}

function closeScheduleCreateModal() {
    document.getElementById('schedule-create-modal').style.display = 'none';
    window._scPending = null;
}

function confirmScheduleCreate() {
    const sc = window._scPending; if (!sc) return;
    const className = document.getElementById('sc-class-name').value.trim() || (sc.courseName + '班');
    const startDate = document.getElementById('sc-start-date').value;
    const endDate = document.getElementById('sc-end-date').value;
    const maxStudents = parseInt(document.getElementById('sc-max-students').value) || 15;
    const lessonHours = parseInt(document.getElementById('sc-lesson-hours').value) || 2;
    if (!startDate) { showToast('请选择开课日期', 'warn'); return; }
    if (!endDate) { showToast('请选择结课日期', 'warn'); return; }
    if (new Date(endDate) <= new Date(startDate)) { showToast('结课日期必须晚于开课日期', 'warn'); return; }
    var campus = sc.campus || document.getElementById('filter-schedule-campus').value;
    if (!campus) { showToast('请先在筛选栏选择校区', 'warn'); return; }
    api('create_schedule_from_grid', {
        course_id: sc.courseId, teacher: sc.teacher, classroom: sc.classroom, campus: campus,
        day_of_week: sc.dayOfWeek, time_start: sc.timeStart, time_end: sc.timeEnd,
        start_date: startDate, end_date: endDate, class_name: className, max_students: maxStudents, lesson_hours: lessonHours
    }, 'POST').then(res => {
        if (res.error) { showToast(res.error, 'error'); return; }
        closeScheduleCreateModal();
        delete pendingCells[sc.cellId];
        loadScheduleView();
        showToast('排课创建成功！班级：' + res.class_name, 'success');
    }).catch(e => { showToast('创建失败: ' + e.message, 'error'); });
}

// ===== 确收统计（复用现金流统计逻辑） =====
function initRevenueDateRange() {
    var now = new Date();
    document.getElementById('rv-date-from').value = new Date(now.getFullYear(), now.getMonth() - 11, 1).toISOString().slice(0, 7);
    document.getElementById('rv-date-to').value = now.toISOString().slice(0, 7);
    initRevenueCampusTree();
}

async function initRevenueCampusTree() {
    try {
        const res = await fetch(API_BASE + 'list_organizations');
        const data = await res.json();
        const flat = (data.data && data.data.flat) ? data.data.flat : [];
        const regions = flat.filter(o => o.type === '部门' && o.parent_id === '0');
        const campuses = flat.filter(o => o.type === '校区');
        var regionMap = {};
        regions.forEach(r => { regionMap[r.id] = { name: r.name, campuses: [] }; });
        campuses.forEach(c => {
            var pid = String(c.parent_id);
            if (regionMap[pid]) regionMap[pid].campuses.push(c);
        });
        var html = '';
        for (var rid in regionMap) {
            var reg = regionMap[rid];
            html += '<div class="cf-tree-region">';
            html += '<label class="cf-tree-check"><input type="checkbox" class="cf-region-cb rv-region-cb" onchange="updateRevenueRegion(this)" checked> ' + esc(reg.name) + '</label>';
            html += '<div class="cf-tree-child">';
            reg.campuses.forEach(c => {
                html += '<label class="cf-tree-check"><input type="checkbox" class="cf-campus-cb rv-campus-cb" value="' + esc(c.name) + '" checked onchange="updateRevenueAllCheckbox()"> ' + esc(c.name) + '</label>';
            });
            html += '</div></div>';
        }
        document.getElementById('rv-campus-tree').innerHTML = html || '<span style="color:#999;font-size:13px;">暂无校区</span>';
    } catch(e) { /* ignore */ }
}

function toggleRevenueCampusTree() {
    var dd = document.getElementById('rv-campus-dropdown');
    if (dd) dd.style.display = dd.style.display === 'none' ? 'block' : 'none';
}

function toggleAllRevenueCampuses() {
    var allCb = document.getElementById('rv-campus-all');
    if (!allCb) return;
    var checked = allCb.checked;
    document.querySelectorAll('.rv-region-cb').forEach(c => { c.checked = checked; c.indeterminate = false; });
    document.querySelectorAll('.rv-campus-cb').forEach(c => { c.checked = checked; });
    updateRevenueCampusText();
}

function updateRevenueRegion(el) {
    var allChecked = true, noneChecked = true;
    el.closest('.cf-tree-region').querySelectorAll('.rv-campus-cb').forEach(c => {
        c.checked = el.checked;
        if (el.checked) noneChecked = false; else allChecked = false;
    });
    if (el.checked) { el.indeterminate = false; }
    updateRevenueAllCheckbox();
    updateRevenueCampusText();
}

function updateRevenueAllCheckbox() {
    var allCampus = document.querySelectorAll('.rv-campus-cb');
    if (allCampus.length === 0) return;
    var allCb = document.getElementById('rv-campus-all');
    var allChecked = Array.from(allCampus).every(c => c.checked);
    var noneChecked = Array.from(allCampus).every(c => !c.checked);
    allCb.checked = allChecked;
    allCb.indeterminate = !allChecked && !noneChecked;
    updateRevenueCampusText();
}

function updateRevenueCampusText() {
    var checked = document.querySelectorAll('.rv-campus-cb:checked');
    var total = document.querySelectorAll('.rv-campus-cb').length;
    var text = checked.length === total ? '全部校区' : checked.length + '个校区';
    document.getElementById('rv-campus-text').textContent = text;
}

function getSelectedRevenueCampuses() {
    var checked = document.querySelectorAll('.rv-campus-cb:checked');
    return Array.from(checked).map(c => c.parentElement.textContent.trim());
}

async function loadRevenue() {
    try {
        var granularity = document.getElementById('rv-granularity')?.value || 'monthly';
        var dateFrom = document.getElementById('rv-date-from')?.value || '';
        var dateTo = document.getElementById('rv-date-to')?.value || '';
        var params = new URLSearchParams({ granularity: granularity });
        var selectedCampuses = getSelectedRevenueCampuses();
        var totalCampuses = document.querySelectorAll('.rv-campus-cb').length;
        if (selectedCampuses.length > 0 && selectedCampuses.length < totalCampuses) {
            params.set('campuses', selectedCampuses.join(','));
        }
        if (dateFrom) params.set('date_from', dateFrom);
        if (dateTo) params.set('date_to', dateTo);
        var res = await fetch(API_BASE + 'get_revenue_stats&' + params);
        var data = await res.json();
        // Reuse cashflow rendering with rv- prefixed IDs
        var s = data.summary || {};
        document.getElementById('rv-total-income').textContent = '¥' + (parseFloat(s.total_income) || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2 });
        document.getElementById('rv-total-expense').textContent = '¥' + (parseFloat(s.total_expense) || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2 });
        var net = parseFloat(s.net_cashflow) || 0;
        var netEl = document.getElementById('rv-net-cashflow');
        netEl.textContent = (net >= 0 ? '¥' : '-¥') + Math.abs(net).toLocaleString('zh-CN', { minimumFractionDigits: 2 });
        netEl.style.color = net >= 0 ? 'var(--color-success)' : 'var(--color-danger)';
        // Table
        var rows = data.data || [];
        var tbody = document.querySelector('#table-revenue tbody');
        if (!rows.length) { tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#999;padding:30px;">暂无数据</td></tr>'; return; }
        tbody.innerHTML = rows.map(function(r) {
            var incAmt = parseFloat(r.income_amount) || 0, expAmt = parseFloat(r.expense_amount) || 0;
            var netR = parseFloat(r.net) || 0;
            var cls = netR >= 0 ? 'color:var(--color-success);' : 'color:var(--color-danger);';
            return '<tr><td>' + esc(r.campus) + '</td><td>' + esc(r.date) + '</td><td>' + r.income_cnt + '</td><td>¥' + incAmt.toLocaleString('zh-CN', {minimumFractionDigits:2}) + '</td><td>' + r.expense_cnt + '</td><td>¥' + expAmt.toLocaleString('zh-CN', {minimumFractionDigits:2}) + '</td><td style="font-weight:bold;' + cls + '">' + (netR>=0?'¥':'-¥') + Math.abs(netR).toLocaleString('zh-CN', {minimumFractionDigits:2}) + '</td></tr>';
        }).join('');
        // Charts (simplified — single color columns)
        renderRevenueCharts(data);
    } catch(e) {
        console.error('loadRevenue error:', e);
    }
}

var rvBarChart = null, rvExpenseChart = null, rvLineChart = null, rvRankChart = null;
function renderRevenueCharts(data) {
    var rows = data.data || [];
    if (!rows.length) return;
    var dateAgg = {}, dateOrder = [];
    rows.forEach(function(r) {
        if (!dateAgg[r.date]) { dateAgg[r.date] = { income:0, expense:0, net:0 }; dateOrder.push(r.date); }
        dateAgg[r.date].income += parseFloat(r.income_amount) || 0;
        dateAgg[r.date].expense += parseFloat(r.expense_amount) || 0;
        dateAgg[r.date].net += parseFloat(r.net) || 0;
    });
    dateOrder.sort();
    var dates = dateOrder;
    var incomeData = dates.map(function(d) { return dateAgg[d].income; });
    var expenseData = dates.map(function(d) { return dateAgg[d].expense; });
    var netData = dates.map(function(d) { return dateAgg[d].net; });

    var green = 'rgba(56,161,105,0.85)', red = 'rgba(229,62,62,0.85)', purple = 'rgba(124,58,237,0.85)';
    var chartOpts = function(label, color) { return { type:'bar', data:{ labels:dates, datasets:[{ label:label, data:null, backgroundColor:color, borderColor:color, borderWidth:1 }] }, options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true } } } }; };

    if (rvBarChart) rvBarChart.destroy();
    var ctx1 = document.getElementById('rv-bar-chart');
    if (ctx1) { rvBarChart = new Chart(ctx1, chartOpts('总收入', green)); rvBarChart.data.datasets[0].data = incomeData; rvBarChart.update(); }

    if (rvExpenseChart) rvExpenseChart.destroy();
    var ctx2 = document.getElementById('rv-expense-chart');
    if (ctx2) { rvExpenseChart = new Chart(ctx2, chartOpts('总支出', red)); rvExpenseChart.data.datasets[0].data = expenseData; rvExpenseChart.update(); }

    if (rvLineChart) rvLineChart.destroy();
    var ctx3 = document.getElementById('rv-line-chart');
    if (ctx3) { rvLineChart = new Chart(ctx3, chartOpts('净现金流', purple)); rvLineChart.data.datasets[0].data = netData; rvLineChart.config.type = 'bar'; rvLineChart.update(); }

    // Rankings chart
    if (rvRankChart) rvRankChart.destroy();
    var ctx4 = document.getElementById('rv-rank-chart');
    var rankings = data.rankings || [];
    if (ctx4 && rankings.length > 0) {
        var rankCampus = rankings.map(function(r) { return r.campus; });
        rvRankChart = new Chart(ctx4, {
            type: 'bar', data: { labels: rankCampus, datasets: [
                { label:'总收入', data: rankings.map(function(r){return r.income;}), backgroundColor: green },
                { label:'总支出', data: rankings.map(function(r){return r.expense;}), backgroundColor: red },
                { label:'净现金流', data: rankings.map(function(r){return r.net;}), backgroundColor: purple }
            ]}, options: { responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true } } }
        });
    }
}

function onRevenueGranularityChange() { loadRevenue(); }

document.addEventListener('click', function(e) {
    var wrap = document.getElementById('rv-campus-wrap');
    var dd = document.getElementById('rv-campus-dropdown');
    if (wrap && dd && !wrap.contains(e.target)) { dd.style.display = 'none'; }
});

// 分段控件 radio→pill 切换
document.addEventListener('change', function(e) {
    var rb = e.target;
    if (rb.type !== 'radio' || !rb.closest('.segmented-control')) return;
    var seg = rb.closest('.segmented-control');
    seg.querySelectorAll('.seg-item').forEach(function(el) { el.classList.remove('active'); });
    rb.parentElement.classList.add('active');
    if (rb.name === 'class_type') onClassTypeChange();
});

// ===== 预约试听 筛选+表格 =====
function showTrialAppointment(resourceId, resourceName, phone) {
    document.getElementById('trial-resource-id').value = resourceId;
    document.getElementById('trial-resource-name').value = resourceName;
    document.getElementById('trial-phone').value = phone || '';
    document.getElementById('trial-info-text').textContent = '客户：' + resourceName + (phone ? ' ' + phone : '');
    loadTrialCampuses();
    loadTrialSubjects();
    loadTrialCourses();
    loadTrialTeachers();
    document.getElementById('trial-table-body').innerHTML = '<tr><td colspan="6" style="text-align:center;color:#bbb;padding:40px;">选择筛选条件后点击查询</td></tr>';
    openModal('modal-appointment');
}
function loadTrialCampuses() {
    api('get_trial_campuses', null, 'GET').then(function(d) {
        var s = document.getElementById('trial-campus');
        s.innerHTML = '<option value="">全部校区</option>';
        (d.data||[]).forEach(function(c){s.innerHTML+='<option value="'+c.id+'">'+c.name+'</option>';});
    });
}
function loadTrialSubjects() {
    var cid = document.getElementById('trial-campus').value;
    api('get_trial_subjects', {campus: cid}, 'GET').then(function(d){
        var s = document.getElementById('trial-subject');
        var cur = s.value;
        s.innerHTML = '<option value="">全部学科</option>';
        (d.data||[]).forEach(function(x){s.innerHTML+='<option value="'+x.name+'">'+x.name+'</option>';});
        s.value = cur;
    });
}
function loadTrialCourses() {
    var cid = document.getElementById('trial-campus').value;
    var subj = document.getElementById('trial-subject').value;
    api('get_trial_courses', {campus: cid, subject: subj}, 'GET').then(function(d){
        var s = document.getElementById('trial-course');
        var cur = s.value;
        s.innerHTML = '<option value="">全部课程</option>';
        (d.data||[]).forEach(function(x){s.innerHTML+='<option value="'+x.id+'">'+x.name+'</option>';});
        s.value = cur;
    });
}
function loadTrialTeachers() {
    var cid = document.getElementById('trial-campus').value;
    // Load teachers from employees filtered by campus
    api('get_employees', null, 'GET').then(function(d) {
        var teachers = (d.data||[]).filter(function(e){return e.is_teacher==='是';});
        if (cid) {
            // Look up campus name
            var campusName = document.getElementById('trial-campus').selectedOptions[0].text;
            teachers = teachers.filter(function(t){return t.department === campusName;});
        }
        var s = document.getElementById('trial-teacher');
        var cur = s.value;
        s.innerHTML = '<option value="">全部老师</option>';
        teachers.forEach(function(t){s.innerHTML+='<option value="'+t.name+'">'+t.name+'</option>';});
        s.value = cur;
    });
}
// Filter cascading: campus/subject change → reload dropdowns only
function onTrialFilterChange() {
    var cid = document.getElementById('trial-campus').value;
    if (cid) {
        loadTrialSubjects();
        loadTrialCourses();
        loadTrialTeachers();
    } else {
        loadTrialSubjects();
        loadTrialCourses();
        loadTrialTeachers();
    }
}
function onTrialSubjectFilterChange() {
    var subj = document.getElementById('trial-subject').value;
    if (subj) { loadTrialCourses(); } else { loadTrialCourses(); }
}
function loadTrialTable() {
    var params = {
        campus: document.getElementById('trial-campus').value,
        subject: document.getElementById('trial-subject').value,
        course: document.getElementById('trial-course').value,
        teacher: document.getElementById('trial-teacher').value,
        date: document.getElementById('trial-date-filter').value
    };
    api('search_trial_sessions', params, 'GET').then(function(d) {
        var rows = d.data || [];
        var tbody = document.getElementById('trial-table-body');
        if (!rows.length) { tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#999;padding:40px;">无匹配课次</td></tr>'; return; }
        tbody.innerHTML = rows.map(function(r) {
            return '<tr><td>'+escHtml(r.class_name)+'</td><td>'+r.date+' '+r.day+' '+r.start+'-'+r.end+'</td><td>'+escHtml(r.campus)+'</td><td>'+escHtml(r.teacher||'')+'</td><td>'+escHtml(r.classroom||'')+'</td><td style="text-align:center;"><a href="javascript:void(0)" onclick="bookTrialInline('+r.class_id+','+r.schedule_id+',\''+r.date+'\',\''+escAttr(r.start)+'\')" style="color:var(--color-primary);font-size:12px;">预约试听</a></td></tr>';
        }).join('');
    });
}
function bookTrialInline(classId, scheduleId, trialDate, startTime) {
    var data = {
        resource_id: parseInt(document.getElementById('trial-resource-id').value)||0,
        course_id: parseInt(document.getElementById('trial-course').value)||0,
        class_id: classId,
        schedule_id: scheduleId,
        trial_date: trialDate,
        campus: document.getElementById('trial-campus').value,
        subject_level1: document.getElementById('trial-subject').value,
        resource_name: document.getElementById('trial-resource-name').value,
        phone: document.getElementById('trial-phone').value
    };
    api('book_trial', data, 'POST').then(function(res) {
        if (res.error) { showToast(res.error,'error'); return; }
        showToast('预约成功！试听日期：'+trialDate+' '+startTime, 'success');
        loadTrialTable();
        if (typeof loadAppointments === 'function') loadAppointments();
    }).catch(function(e) { showToast('预约失败: '+(e.message||String(e)),'error'); });
}

/* ==================== Flatpickr 统一初始化 ==================== */
function initDatePickers(selector, overrides) {
    selector = selector || 'input[type="date"]';
    overrides = overrides || {};
    var instances = [];
    var defaults = {
        locale: 'zh',
        dateFormat: 'Y-m-d',
        allowInput: false,
        disableMobile: true,
        static: false,
        showMonths: 1,
        monthSelectorType: 'static',
        prevArrow: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="15 18 9 12 15 6"/></svg>',
        nextArrow: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="9 6 15 12 9 18"/></svg>',
    };
    var config = Object.assign({}, defaults, overrides);
    var inputs = document.querySelectorAll(selector);
    inputs.forEach(function(el) {
        if (el.classList.contains('flatpickr-input')) return;
        if (el.offsetParent === null && el.style.display === 'none') return;
        if (el._flatpickr) { el._flatpickr.destroy(); }
        var fp = flatpickr(el, config);
        instances.push(fp);
    });
    window.__fpInstances = instances;
    return instances;
}

document.addEventListener('DOMContentLoaded', function() {
    initDatePickers('input[type="date"]');
});

function refreshDatePickers(containerSelector) {
    var scope = containerSelector ? document.querySelector(containerSelector) : document;
    if (!scope) scope = document;
    var inputs = scope.querySelectorAll('input[type="date"]:not(.flatpickr-input)');
    inputs.forEach(function(el) {
        if (el.offsetParent === null && el.style.display === 'none') return;
        flatpickr(el, { locale: 'zh', dateFormat: 'Y-m-d', allowInput: false, disableMobile: true });
    });
}

// ── 学员账户 ====================
let accountAllTransactions = [];
let accountCurrentPage = 1;
const accountPageSize = 20;

async function loadStudentAccount(sid) {
    const tbody = document.getElementById('account-transactions-tbody');
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#999;padding:20px;">加载中...</td></tr>';
    // Reset filters
    document.getElementById('account-filter-type').value = '';
    document.getElementById('account-filter-date-from').value = '';
    document.getElementById('account-filter-date-to').value = '';
    try {
        const res = await fetch(API_BASE + 'get_student_account&student_id=' + sid + '&page_size=10000');
        const data = await res.json();
        // Update balance cards
        document.getElementById('account-balance').textContent = '¥' + Number(data.balance || 0).toLocaleString('zh-CN', {minimumFractionDigits: 2});
        document.getElementById('account-total-deposit').textContent = '¥' + Number(data.total_deposit || 0).toLocaleString('zh-CN', {minimumFractionDigits: 2});
        document.getElementById('account-total-consume').textContent = '¥' + Number(data.total_consume || 0).toLocaleString('zh-CN', {minimumFractionDigits: 2});
        document.getElementById('account-total-refund').textContent = '¥' + Number(data.total_refund || 0).toLocaleString('zh-CN', {minimumFractionDigits: 2});
        // 更新「申请退费」按钮状态
        const btn = document.getElementById('btn-account-refund');
        if (btn) {
            const bal = Number(data.balance || 0);
            btn.disabled = (bal <= 0);
            btn.title = bal <= 0 ? '账户余额为0，无法申请退费' : '申请将余额退至银行卡';
            btn.style.opacity = bal <= 0 ? '0.5' : '1';
            btn.style.cursor = bal <= 0 ? 'not-allowed' : 'pointer';
        }
        accountAllTransactions = data.transactions || [];
        accountCurrentPage = 1;
        renderAccountTransactions(accountAllTransactions, 1);
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#e74c3c;padding:20px;">加载失败</td></tr>';
    }
}

function typeTag(type) {
    const map = { deposit: ['充值', 'type-tag-deposit'], consume: ['消费', 'type-tag-consume'], refund: ['退款', 'type-tag-refund'] };
    const [label, cls] = map[type] || [type, ''];
    return '<span class="type-tag ' + cls + '">' + esc(label) + '</span>';
}

function formatAccountAmount(type, amount) {
    const n = Number(amount || 0);
    if (type === 'consume') return '<span style="color:#e74c3c;">-' + n.toLocaleString('zh-CN', {minimumFractionDigits: 2}) + '</span>';
    return '<span style="color:#27ae60;">+' + n.toLocaleString('zh-CN', {minimumFractionDigits: 2}) + '</span>';
}

function renderAccountTransactions(transactions, page) {
    const tbody = document.getElementById('account-transactions-tbody');
    const totalPages = Math.ceil(transactions.length / accountPageSize) || 1;
    if (page > totalPages) page = totalPages;
    if (page < 1) page = 1;
    accountCurrentPage = page;
    const start = (page - 1) * accountPageSize;
    const rows = transactions.slice(start, start + accountPageSize);
    if (rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#999;padding:20px;">暂无交易流水</td></tr>';
    } else {
        tbody.innerHTML = rows.map(function(r) {
            return '<tr>' +
                '<td>' + esc(r.created_at || '') + '</td>' +
                '<td>' + typeTag(r.type) + '</td>' +
                '<td>' + (r.type === 'deposit' ? esc(r.payment_method || '-') : '-') + '</td>' +
                '<td>' + formatAccountAmount(r.type, r.amount) + '</td>' +
                '<td>¥' + Number(r.balance_after || 0).toLocaleString('zh-CN', {minimumFractionDigits: 2}) + '</td>' +
                '<td>' + (esc(r.ref_no) || '-') + '</td>' +
                '<td>' + esc(r.campus || '-') + '</td>' +
                '<td>' + esc(r.note || '-') + '</td>' +
                '</tr>';
        }).join('');
    }
    // Pagination
    var pagHTML = '';
    if (totalPages > 1) {
        pagHTML += '<button class="btn btn-sm" ' + (page <= 1 ? 'disabled' : '') + ' onclick="renderAccountTransactions(accountFilteredTransactions(), ' + (page - 1) + ')">上一页</button>';
        for (var i = 1; i <= totalPages; i++) {
            if (i === page) pagHTML += '<span class="page-current">' + i + '</span>';
            else pagHTML += '<button class="btn btn-sm btn-page" onclick="renderAccountTransactions(accountFilteredTransactions(), ' + i + ')">' + i + '</button>';
        }
        pagHTML += '<button class="btn btn-sm" ' + (page >= totalPages ? 'disabled' : '') + ' onclick="renderAccountTransactions(accountFilteredTransactions(), ' + (page + 1) + ')">下一页</button>';
        pagHTML += '<span style="margin-left:8px;font-size:13px;color:#666;">共 ' + transactions.length + ' 条</span>';
    }
    document.getElementById('pagination-account').innerHTML = pagHTML;
}

function accountFilteredTransactions() {
    var typeFilter = document.getElementById('account-filter-type').value;
    var dateFrom = document.getElementById('account-filter-date-from').value;
    var dateTo = document.getElementById('account-filter-date-to').value;
    return accountAllTransactions.filter(function(r) {
        if (typeFilter && r.type !== typeFilter) return false;
        if (dateFrom && r.created_at < dateFrom) return false;
        if (dateTo && r.created_at > dateTo + ' 23:59:59') return false;
        return true;
    });
}

function filterAccountTransactions() {
    var filtered = accountFilteredTransactions();
    renderAccountTransactions(filtered, 1);
}

// ── 加载校区 + 一级学科数据（充值弹窗用）──
async function loadCampusAndSubjects() {
    if (campusList.length > 0 && subjectLevel1List.length > 0) return;
    try {
        const [orgRes, subRes] = await Promise.all([
            fetch(API_BASE + 'list_organizations'),
            fetch(API_BASE + 'list_subjects')
        ]);
        const orgData = await orgRes.json();
        const orgs = (orgData.data && orgData.data.flat) || [];
        campusList = orgs
            .filter(o => o.type === '校区')
            .sort((a, b) => (a.name || '').localeCompare(b.name || '', 'zh-CN'));

        const subData = await subRes.json();
        const subs = subData.flat || [];
        subjectLevel1List = subs
            .filter(s => s.parent_id == 0)
            .sort((a, b) => (a.name || '').localeCompare(b.name || '', 'zh-CN'));
    } catch (e) {
        console.error('loadCampusAndSubjects error:', e);
    }
}

function showRechargeModal() {
    var existing = document.querySelector('.modal-overlay');
    if (existing) existing.remove();
    var overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.4);z-index:9999;display:flex;align-items:center;justify-content:center;';

    // 构建下拉的 helper：数据已加载则用真实数据，否则占位
    function buildCampusOptions() {
        if (campusList.length === 0) return '<option value="">加载中...</option>';
        var opts = '<option value="">请选择校区</option>';
        campusList.forEach(function(c) { opts += '<option value="' + escHtml(c.name) + '">' + escHtml(c.name) + '</option>'; });
        return opts;
    }
    function buildSubjectOptions() {
        if (subjectLevel1List.length === 0) return '<option value="">加载中...</option>';
        var opts = '<option value="">请选择学科</option>';
        subjectLevel1List.forEach(function(s) { opts += '<option value="' + escHtml(s.name) + '">' + escHtml(s.name) + '</option>'; });
        return opts;
    }

    overlay.innerHTML = '<div class="modal-content" style="background:#fff;border-radius:10px;padding:24px;max-width:440px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,0.2);">' +
        '<h3 style="margin:0 0 20px;font-size:18px;">账户充值</h3>' +
        // ① 金额
        '<div style="margin-bottom:14px;">' +
            '<label style="display:block;font-size:13px;color:#666;margin-bottom:6px;">充值金额 <span style="color:#e74c3c;">*</span></label>' +
            '<input type="number" id="recharge-amount" placeholder="请输入充值金额" step="0.01" min="0.01" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;box-sizing:border-box;">' +
        '</div>' +
        // ② 支付方式
        '<div style="margin-bottom:14px;">' +
            '<label style="display:block;font-size:13px;color:#666;margin-bottom:6px;">支付方式</label>' +
            '<select id="recharge-payment-method" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;box-sizing:border-box;">' +
                '<option value="现金">现金</option>' +
                '<option value="微信">微信</option>' +
                '<option value="支付宝">支付宝</option>' +
                '<option value="银行卡">银行卡</option>' +
                '<option value="转账">转账</option>' +
            '</select>' +
        '</div>' +
        // ③ 校区（必选）
        '<div style="margin-bottom:14px;">' +
            '<label style="display:block;font-size:13px;color:#666;margin-bottom:6px;">校区 <span style="color:#e74c3c;">*</span></label>' +
            '<select id="recharge-campus" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;box-sizing:border-box;">' +
                buildCampusOptions() +
            '</select>' +
        '</div>' +
        // ④ 一级学科（必选）
        '<div style="margin-bottom:14px;">' +
            '<label style="display:block;font-size:13px;color:#666;margin-bottom:6px;">一级学科 <span style="color:#e74c3c;">*</span></label>' +
            '<select id="recharge-subject" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;box-sizing:border-box;">' +
                buildSubjectOptions() +
            '</select>' +
        '</div>' +
        // ⑤ 备注
        '<div style="margin-bottom:20px;">' +
            '<label style="display:block;font-size:13px;color:#666;margin-bottom:6px;">备注</label>' +
            '<input type="text" id="recharge-note" placeholder="可选备注" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px;box-sizing:border-box;">' +
        '</div>' +
        '<div style="display:flex;gap:12px;justify-content:flex-end;">' +
            '<button class="btn" onclick="this.closest(\'.modal-overlay\').remove()" style="padding:8px 20px;">取消</button>' +
            '<button class="btn btn-primary" onclick="submitRecharge()" style="padding:8px 20px;">确认充值</button>' +
        '</div>' +
    '</div>';
    document.body.appendChild(overlay);
    // Click overlay background to close
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) overlay.remove();
    });
    // 如果数据尚未加载，异步加载后刷新下拉
    if (campusList.length === 0 || subjectLevel1List.length === 0) {
        loadCampusAndSubjects().then(function() {
            var campusSel = document.getElementById('recharge-campus');
            var subjectSel = document.getElementById('recharge-subject');
            if (campusSel && campusList.length > 0) {
                campusSel.innerHTML = '<option value="">请选择校区</option>';
                campusList.forEach(function(c) { campusSel.innerHTML += '<option value="' + escHtml(c.name) + '">' + escHtml(c.name) + '</option>'; });
            }
            if (subjectSel && subjectLevel1List.length > 0) {
                subjectSel.innerHTML = '<option value="">请选择学科</option>';
                subjectLevel1List.forEach(function(s) { subjectSel.innerHTML += '<option value="' + escHtml(s.name) + '">' + escHtml(s.name) + '</option>'; });
            }
        });
    }
    // Focus amount input
    setTimeout(function() {
        var inp = document.getElementById('recharge-amount');
        if (inp) inp.focus();
    }, 100);
}

async function submitRecharge() {
    var amount = parseFloat(document.getElementById('recharge-amount').value);
    if (!amount || amount <= 0) { showToast('请输入有效的充值金额', 'error'); return; }
    var method = document.getElementById('recharge-payment-method').value;
    var campus = document.getElementById('recharge-campus').value;
    if (!campus) { showToast('请选择校区', 'error'); return; }
    var subject = document.getElementById('recharge-subject').value;
    if (!subject) { showToast('请选择一级学科', 'error'); return; }
    var note = document.getElementById('recharge-note').value.trim();
    var sid = currentViewStudentId;
    if (!sid) { showToast('学员信息丢失，请重新打开详情', 'error'); return; }
    try {
        var res = await api('top_up_account', { student_id: sid, amount: amount, payment_method: method, campus: campus, subject_level1: subject, note: note }, 'POST');
        if (res.error) { showToast(res.error, 'error'); return; }
        showToast(res.message || '充值成功');
        document.querySelectorAll('.modal-overlay').forEach(function(m) { m.remove(); });
        loadStudentAccount(sid);
    } catch (e) {
        showToast('充值请求失败', 'error');
    }
}

// ── 移动端侧边栏切换 ──
function toggleSidebar() {
    var sidebar = document.querySelector('.sidebar');
    var overlay = document.querySelector('.sidebar-overlay');
    sidebar.classList.toggle('mobile-open');
    if (overlay) overlay.classList.toggle('active');
}
