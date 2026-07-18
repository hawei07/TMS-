#!/usr/bin/env python3
"""Refactor TMS enroll flow HTML in index.php:
1. Move campus dropdown to shared position before type cards
2. Move type cards inside enroll-form, initially hidden
3. Course flow hidden by default, no campus dropdown
4. Activity flow without Step1 campus cards, steps renumbered
"""

import sys

path = 'D:/market-system-php/index.php'

with open(path, 'r', encoding='utf-8') as f:
    content = f.read()

# --- CHANGE 1: Replace type-cards + enroll-form start + course-flow start ---
# Old: type cards OUTSIDE enroll-form, then enroll-form, then course-flow with campus dropdown
old1 = '''                </div>

                <!-- ★ 新增：类型选择（学员信息卡片之后） -->
                <div class="enroll-type-select" id="enroll-type-select">
                    <div class="enroll-type-grid">
                        <!-- 报名课程卡片 -->
                        <div class="enroll-type-card active" id="enroll-type-course" onclick="selectEnrollType('course')">
                            <div class="enroll-type-card-icon">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                                    <line x1="8" y1="7" x2="16" y2="7"/>
                                    <line x1="8" y1="11" x2="14" y2="11"/>
                                </svg>
                            </div>
                            <div class="enroll-type-card-label">报名课程</div>
                            <div class="enroll-type-card-desc">选择校区 → 课程 → 价格方案 → 支付</div>
                        </div>
                        <!-- 报名活动卡片 -->
                        <div class="enroll-type-card" id="enroll-type-activity" onclick="selectEnrollType('activity')">
                            <div class="enroll-type-card-icon">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polygon points="10,8 16,12 10,16"/>
                                </svg>
                            </div>
                            <div class="enroll-type-card-label">报名活动</div>
                            <div class="enroll-type-card-desc">选择活动 → 填写人数 → 支付报名费</div>
                        </div>
                    </div>
                </div>

                <!-- 表单区域 -->
                <div class="enroll-form">
                    <!-- 课程报名流程容器 -->
                    <div class="enroll-course-flow" id="enroll-course-flow">
                    <!-- 校区/课程 — 上下布局 -->
                    <div class="enroll-form-row" style="flex-direction: column; gap: 16px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>校区 <span class="required">*</span></label>
                            <select id="enroll-campus-select"><option value="">请选择校区</option></select>
                        </div>
                        <div class="enroll-course-picker" id="enroll-course-picker">
                            <label>课程 <span class="required">*</span></label>'''

new1 = '''                </div>

                <!-- 表单区域 -->
                <div class="enroll-form">
                    <!-- 公用：选择校区 -->
                    <div class="enroll-form-row" id="enroll-common-campus" style="flex-direction: column; gap: 16px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>校区 <span class="required">*</span></label>
                            <select id="enroll-campus-select"><option value="">请选择校区</option></select>
                        </div>
                    </div>

                    <!-- 选择课程/活动（校区选定后显示） -->
                    <div class="enroll-type-select" id="enroll-type-select" style="display:none;">
                        <div class="enroll-type-grid">
                            <!-- 报名课程卡片 -->
                            <div class="enroll-type-card active" id="enroll-type-course" onclick="selectEnrollType('course')">
                                <div class="enroll-type-card-icon">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                                        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                                        <line x1="8" y1="7" x2="16" y2="7"/>
                                        <line x1="8" y1="11" x2="14" y2="11"/>
                                    </svg>
                                </div>
                                <div class="enroll-type-card-label">报名课程</div>
                                <div class="enroll-type-card-desc">选择课程 → 方案 → 支付</div>
                            </div>
                            <!-- 报名活动卡片 -->
                            <div class="enroll-type-card" id="enroll-type-activity" onclick="selectEnrollType('activity')">
                                <div class="enroll-type-card-icon">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/>
                                        <polygon points="10,8 16,12 10,16"/>
                                    </svg>
                                </div>
                                <div class="enroll-type-card-label">报名活动</div>
                                <div class="enroll-type-card-desc">选择活动 → 人数 → 支付</div>
                            </div>
                        </div>
                    </div>

                    <!-- 课程报名流程容器（默认隐藏） -->
                    <div class="enroll-course-flow" id="enroll-course-flow" style="display:none;">
                    <!-- 课程 — 上下布局 -->
                    <div class="enroll-form-row" style="flex-direction: column; gap: 16px;">
                        <div class="enroll-course-picker" id="enroll-course-picker">
                            <label>课程 <span class="required">*</span></label>'''

count = content.count(old1)
if count != 1:
    print(f"ERROR: old1 found {count} times, expected 1")
    sys.exit(1)
content = content.replace(old1, new1)
print("Change 1 applied: campus dropdown extracted, type cards moved inside enroll-form")

# --- CHANGE 2: Remove Step 1 campus cards from activity flow, renumber steps ---
old2 = '''                    <!-- ★ 新增：活动报名流程容器（默认隐藏） -->
                    <div class="enroll-activity-flow" id="enroll-activity-flow" style="display:none;">

                        <!-- Step 1: 选择校区 -->
                        <div class="enroll-section enroll-step" id="enroll-activity-step-campus">
                            <div class="enroll-section-title">
                                <span class="enroll-step-num">1</span> 选择校区
                            </div>
                            <div class="activity-campus-grid" id="activity-campus-grid">
                                <!-- JS 动态渲染校区卡片 -->
                            </div>
                            <div class="activity-campus-empty" id="activity-campus-empty" style="display:none;">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/></svg>
                                <p>暂无可用校区</p>
                            </div>
                        </div>

                        <!-- Step 2: 选择活动 -->
                        <div class="enroll-section enroll-step" id="enroll-activity-step-activity" style="display:none;">
                            <div class="enroll-section-title">
                                <span class="enroll-step-num">2</span> 选择活动
                            </div>'''

new2 = '''                    <!-- 活动报名流程容器（默认隐藏） -->
                    <div class="enroll-activity-flow" id="enroll-activity-flow" style="display:none;">

                        <!-- Step 1: 选择活动 -->
                        <div class="enroll-section enroll-step" id="enroll-activity-step-activity" style="display:none;">
                            <div class="enroll-section-title">
                                <span class="enroll-step-num">1</span> 选择活动
                            </div>'''

count = content.count(old2)
if count != 1:
    print(f"ERROR: old2 found {count} times, expected 1")
    sys.exit(1)
content = content.replace(old2, new2)
print("Change 2 applied: removed Step 1 campus cards from activity flow")

# --- CHANGE 3: Renumber activity Step 3 → Step 2 (人数) ---
old3 = '''                        <!-- Step 3: 填写报名人数 -->
                        <div class="enroll-section enroll-step" id="enroll-activity-step-count" style="display:none;">
                            <div class="enroll-section-title">
                                <span class="enroll-step-num">3</span> 填写报名人数
                            </div>'''

new3 = '''                        <!-- Step 2: 填写报名人数 -->
                        <div class="enroll-section enroll-step" id="enroll-activity-step-count" style="display:none;">
                            <div class="enroll-section-title">
                                <span class="enroll-step-num">2</span> 填写报名人数
                            </div>'''

count = content.count(old3)
if count != 1:
    print(f"ERROR: old3 found {count} times, expected 1")
    sys.exit(1)
content = content.replace(old3, new3)
print("Change 3 applied: renumbered activity Step 3 → Step 2")

# --- CHANGE 4: Renumber activity Step 4 → Step 3 (支付) ---
old4 = '''                        <!-- Step 4: 确认支付（复用现有支付 UI） -->
                        <div class="enroll-section enroll-step" id="enroll-activity-step-pay" style="display:none;">
                            <div class="enroll-section-title">
                                <span class="enroll-step-num">4</span> 确认支付
                            </div>'''

new4 = '''                        <!-- Step 3: 确认支付（复用现有支付 UI） -->
                        <div class="enroll-section enroll-step" id="enroll-activity-step-pay" style="display:none;">
                            <div class="enroll-section-title">
                                <span class="enroll-step-num">3</span> 确认支付
                            </div>'''

count = content.count(old4)
if count != 1:
    print(f"ERROR: old4 found {count} times, expected 1")
    sys.exit(1)
content = content.replace(old4, new4)
print("Change 4 applied: renumbered activity Step 4 → Step 3")

# --- CHANGE 5: Update activity step comment in action bar area ---
old5 = '''                        <!-- 活动流程操作栏 -->'''

new5 = '''                        <!-- 活动流程操作栏（Step 1:选活动, Step 2:填人数, Step 3:支付） -->'''

count = content.count(old5)
if count != 1:
    print(f"ERROR: old5 found {count} times, expected 1")
    sys.exit(1)
content = content.replace(old5, new5)
print("Change 5 applied: updated activity action bar comment")

# Change 6: Update the card description for course
old6 = '选择课程 → 方案 → 支付'
if old6 not in content:
    print("Change 6 already applied (was in change 1)")
else:
    print("Change 6: course card desc already updated in change 1")

# Write back
with open(path, 'w', encoding='utf-8') as f:
    f.write(content)

print("\nAll HTML changes applied successfully!")
print(f"File size: {len(content)} bytes")
