<template>
  <div class="anniversary-container">
    <AppHeader title="纪念日" />
    
    <main class="anniversary-main">
      <!-- 主要纪念日卡片 -->
      <div class="anniv-info anniv-fancy">
        <div class="anniv-title">💕 我们已经在一起</div>
        <div class="anniv-days-row">
          <span id="days-count" class="anniv-days">{{ daysCount }}</span>
          <span class="anniv-days-label">天</span>
        </div>
        <div class="anniv-subtitle">希望我们永远在一起~</div>
      </div>

      <!-- 添加按钮 -->
      <div class="add-anniversary-container">
        <button type="button" class="add-btn-modern" @click="openAddModal">
          <span class="add-btn-icon">✨</span>
          <span class="add-btn-text">添加新纪念日</span>
          <span class="add-btn-plus">+</span>
        </button>
      </div>

      <!-- 公共纪念日列表 -->
      <div class="public-anniversaries-section">
        <h2>所有纪念日</h2>
        <div id="public-anniversaries-list">
          <LoadingSpinner v-if="loading" text="正在加载..." />
          <div v-else-if="anniversaries.length === 0" style="text-align: center; padding: 20px;">暂无纪念日数据</div>
          
          <div class="anniv-card" v-for="item in anniversaries" :key="item.id">
            <div class="anniv-card-header">
              <span class="anniv-card-title">{{ item.name }}</span>
              <div class="anniv-card-actions" v-if="isOwnAnniversary(item)">
                <button type="button" class="anniv-edit-btn" @click="openEditModal(item)" title="编辑">✏️</button>
                <button type="button" class="anniv-delete-btn" @click="deleteAnniv(item.id)" title="删除">🗑️</button>
              </div>
            </div>
            <div class="anniv-card-date">{{ item.dateLabel }}</div>
            <div class="anniv-card-desc" v-if="item.description">{{ item.description }}</div>
            <div class="anniv-card-countdown" :class="{ 'is-passed': item.count_status === 'passed' }">
              <template v-if="item.count_status === 'passed'">
                <span>已经过去了</span>
                <span class="countdown-days">{{ item.days_since }}</span>
                <span>天</span>
              </template>
              <template v-else-if="item.count_status === 'today'">
                <span class="countdown-days countdown-today">就是今天</span>
              </template>
              <template v-else>
                <span>还有</span>
                <span class="countdown-days">{{ item.days_left }}</span>
                <span>天</span>
              </template>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- 添加模态框 (简化版) -->
    <div class="modal anniversary-modal-root" v-if="showAddModal" @click.self="closeAddModal">
      <div class="modal-content">
        <div class="modal-header">
          <h2>{{ modalTitle }}</h2>
          <button class="modal-close" type="button" @click="closeAddModal">&times;</button>
        </div>
        <div class="modal-body anniv-modal-body">
          <div class="modal-field-group">
            <label class="modal-field-label" for="anniv-name-input">事件名称</label>
            <input id="anniv-name-input" type="text" v-model="newAnniv.name" class="modal-input-full" placeholder="例如：社区成立日">
          </div>

          <div class="modal-field-group">
            <span class="modal-field-label">日期类型</span>
            <div class="date-type-segment" role="tablist" aria-label="公历或农历">
              <button
                type="button"
                role="tab"
                :aria-selected="!newAnniv.isLunar"
                class="date-seg-btn"
                :class="{ active: !newAnniv.isLunar }"
                @click="newAnniv.isLunar = false"
              >
                公历
              </button>
              <button
                type="button"
                role="tab"
                :aria-selected="newAnniv.isLunar"
                class="date-seg-btn"
                :class="{ active: newAnniv.isLunar }"
                @click="newAnniv.isLunar = true"
              >
                农历
              </button>
            </div>
          </div>

          <div v-if="!newAnniv.isLunar" class="modal-field-group">
            <label class="modal-field-label" for="anniv-solar-date">公历日期</label>
            <input id="anniv-solar-date" class="modal-input-full" type="date" v-model="newAnniv.date">
          </div>
          <div v-else class="modal-field-group modal-field-group--lunar">
            <span class="modal-field-label">农历日期</span>
            <div class="lunar-inputs-grid">
              <div class="lunar-select-wrap">
                <span class="lunar-select-hint">年</span>
                <select v-model.number="newAnniv.lunarYear" class="lunar-select" title="年" aria-label="农历年">
                  <option v-for="y in lunarYearOptions" :key="y" :value="y">{{ y }}年</option>
                </select>
              </div>
              <div class="lunar-select-wrap">
                <span class="lunar-select-hint">月</span>
                <select v-model.number="newAnniv.lunarMonth" class="lunar-select" title="月" aria-label="农历月">
                  <option v-for="m in 12" :key="m" :value="m">{{ lunarMonthLabels[m - 1] }}</option>
                </select>
              </div>
              <div class="lunar-select-wrap">
                <span class="lunar-select-hint">日</span>
                <select v-model.number="newAnniv.lunarDay" class="lunar-select" title="日" aria-label="农历日">
                  <option v-for="d in lunarDayOptions" :key="d" :value="d">{{ lunarDayCn(d) }}</option>
                </select>
              </div>
            </div>
            <div class="lunar-leap-row">
              <input id="anniv-lunar-leap" v-model="newAnniv.lunarLeap" type="checkbox" class="lunar-leap-checkbox">
              <label for="anniv-lunar-leap" class="lunar-leap-text">闰月（当年该月有闰月时勾选）</label>
            </div>
            <div v-if="lunarPreviewLines" class="lunar-preview-box">
              <div class="lunar-preview-line strong">{{ lunarPreviewLines.lunar }}</div>
              <div class="lunar-preview-line">{{ lunarPreviewLines.solar }}</div>
            </div>
          </div>

          <div class="modal-field-group">
            <label class="modal-field-label" for="anniv-desc">描述</label>
            <textarea id="anniv-desc" v-model="newAnniv.description" class="modal-textarea-full" placeholder="关于这个纪念日的简要描述..." rows="3"></textarea>
          </div>

          <div class="modal-field-group anniv-repeat-row">
            <label class="modal-field-label anniv-repeat-label" for="anniv-repeat-yearly">
              <input id="anniv-repeat-yearly" v-model="newAnniv.repeatYearly" type="checkbox" class="lunar-leap-checkbox">
              <span>每年重复（生日、年节等：过季后自动算到下一次；不勾选则本年已过只显示「已过去」天数）</span>
            </label>
          </div>

          <div class="modal-actions">
            <button type="button" class="btn-save" @click="saveAnniversary">保存</button>
            <button type="button" class="btn-cancel" @click="closeAddModal">取消</button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue';
import { useRouter } from 'vue-router';
import { APP_CONFIG, commonFetch } from '../utils/config';
import { customAlert, customConfirm } from '../utils/modal';
import { lunarCalendar } from '../utils/lunar-calendar';

const router = useRouter();
const currentUserId = ref(localStorage.getItem('user_id'));
const daysCount = ref(0);
const anniversaries = ref([]);
const loading = ref(false);
const showAddModal = ref(false);
const editingId = ref(null);

const lunarYearOptions = Array.from({ length: 201 }, (_, i) => 1900 + i);
const lunarMonthLabels = ['正月', '二月', '三月', '四月', '五月', '六月', '七月', '八月', '九月', '十月', '冬月', '腊月'];

const newAnniv = reactive({
  name: '',
  date: '',
  description: '',
  isLunar: false,
  repeatYearly: false,
  lunarYear: new Date().getFullYear(),
  lunarMonth: 1,
  lunarDay: 1,
  lunarLeap: false
});

function pad2(n) {
  return String(n).padStart(2, '0');
}

function maxLunarDayFor(y, m, isLeap) {
  const leapM = lunarCalendar.leapMonth(y);
  if (isLeap && leapM === m) return lunarCalendar.leapDays(y);
  return lunarCalendar.monthDays(y, m);
}

const lunarDayOptions = computed(() => {
  const y = newAnniv.lunarYear;
  const m = newAnniv.lunarMonth;
  const leap = newAnniv.lunarLeap;
  const max = maxLunarDayFor(y, m, leap);
  return Array.from({ length: max }, (_, i) => i + 1);
});

watch(
  () => [newAnniv.lunarYear, newAnniv.lunarMonth, newAnniv.lunarLeap],
  () => {
    const max = maxLunarDayFor(newAnniv.lunarYear, newAnniv.lunarMonth, newAnniv.lunarLeap);
    if (newAnniv.lunarDay > max) newAnniv.lunarDay = max;
    if (newAnniv.lunarDay < 1) newAnniv.lunarDay = 1;
  }
);

const lunarPreviewLines = computed(() => {
  if (!newAnniv.isLunar) return null;
  const y = newAnniv.lunarYear;
  const m = newAnniv.lunarMonth;
  const d = newAnniv.lunarDay;
  const leap = newAnniv.lunarLeap;
  if (!y || !m || !d) return null;
  const solar = lunarCalendar.lunar2solar(y, m, d, leap);
  if (!solar) {
    return { lunar: '无效的农历日期', solar: '请检查闰月、月份与日期是否匹配' };
  }
  return {
    lunar: lunarCalendar.formatLunarDate(y, m, d, leap),
    solar: `对应公历：${solar.cYear}-${pad2(solar.cMonth)}-${pad2(solar.cDay)}`
  };
});

function lunarDayCn(day) {
  return lunarCalendar.getChinaDay(day);
}

const DAY_MS = 1000 * 60 * 60 * 24;

/** 是否按「每年重复」处理（生日等到下一次；否则本年已过只显示已过去天数） */
function isRepeatYearlyItem(item) {
  const raw = item.repeat_yearly;
  if (raw === true || raw === 1 || raw === '1') return true;
  const title = String(item.title || item.name || '').trim();
  return /生日|诞辰/.test(title);
}

/** 公历：本年对应的月日（不滚到下一年） */
function getThisYearSolarOccurrenceDate(dateStr) {
  if (!dateStr) return null;
  const parsed = new Date(dateStr);
  if (Number.isNaN(parsed.getTime())) return null;
  const y = new Date().getFullYear();
  const d = new Date(y, parsed.getMonth(), parsed.getDate());
  d.setHours(0, 0, 0, 0);
  return d;
}

/** 农历：本年对应的公历日期（不滚到下一年） */
function getThisYearLunarSolarDate(lunarMonth, lunarDay, lunarLeap) {
  const currentYear = new Date().getFullYear();
  const thisYearSolar = lunarCalendar.lunar2solar(currentYear, lunarMonth, lunarDay, lunarLeap);
  if (!thisYearSolar) return null;
  const d = new Date(thisYearSolar.cYear, thisYearSolar.cMonth - 1, thisYearSolar.cDay);
  d.setHours(0, 0, 0, 0);
  return d;
}

/** 公历纪念日：下一次到来的公历日期（与倒计时一致） */
function getNextSolarOccurrenceDate(dateStr) {
  if (!dateStr) return null;
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const parsed = new Date(dateStr);
  if (Number.isNaN(parsed.getTime())) return null;
  const month = parsed.getMonth();
  const day = parsed.getDate();
  let next = new Date(today.getFullYear(), month, day);
  next.setHours(0, 0, 0, 0);
  if (next < today) {
    next = new Date(today.getFullYear() + 1, month, day);
    next.setHours(0, 0, 0, 0);
  }
  return next;
}

/** 农历纪念日：下一次对应的公历日期（与倒计时一致） */
function getNextLunarOccurrenceDate(lunarMonth, lunarDay, lunarLeap) {
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const currentYear = today.getFullYear();
  const thisYearSolar = lunarCalendar.lunar2solar(currentYear, lunarMonth, lunarDay, lunarLeap);
  if (!thisYearSolar) {
    const nextTry = lunarCalendar.lunar2solar(currentYear + 1, lunarMonth, lunarDay, lunarLeap);
    if (!nextTry) return null;
    return new Date(nextTry.cYear, nextTry.cMonth - 1, nextTry.cDay);
  }
  let target = new Date(thisYearSolar.cYear, thisYearSolar.cMonth - 1, thisYearSolar.cDay);
  target.setHours(0, 0, 0, 0);
  if (target < today) {
    const nextYearSolar = lunarCalendar.lunar2solar(currentYear + 1, lunarMonth, lunarDay, lunarLeap);
    if (!nextYearSolar) return null;
    target = new Date(nextYearSolar.cYear, nextYearSolar.cMonth - 1, nextYearSolar.cDay);
  }
  return target;
}

function formatItemDateLabel(item) {
  const repeat = isRepeatYearlyItem(item);
  if (item.is_lunar && item.lunar_year != null && item.lunar_month != null && item.lunar_day != null) {
    try {
      const lunarDisplay = lunarCalendar.formatLunarDate(
        item.lunar_year,
        item.lunar_month,
        item.lunar_day,
        !!item.lunar_leap
      );
      const target = repeat
        ? getNextLunarOccurrenceDate(item.lunar_month, item.lunar_day, !!item.lunar_leap)
        : getThisYearLunarSolarDate(item.lunar_month, item.lunar_day, !!item.lunar_leap);
      if (target) {
        return `${lunarDisplay}（${target.getFullYear()}年公历: ${target.getMonth() + 1}月${target.getDate()}日）`;
      }
      return lunarDisplay;
    } catch {
      return `农历 ${item.date || ''}`;
    }
  }
  if (repeat) {
    const nextSolar = getNextSolarOccurrenceDate(item.date);
    if (nextSolar) {
      return `公历 ${nextSolar.getFullYear()}-${pad2(nextSolar.getMonth() + 1)}-${pad2(nextSolar.getDate())}`;
    }
  } else {
    const ty = getThisYearSolarOccurrenceDate(item.date);
    if (ty) {
      return `公历 ${ty.getFullYear()}-${pad2(ty.getMonth() + 1)}-${pad2(ty.getDate())}`;
    }
  }
  return `公历 ${item.date || ''}`;
}

function buildAnniversaryMetrics(item) {
  const repeat = isRepeatYearlyItem(item);
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  if (item.is_lunar && item.lunar_month != null && item.lunar_day != null) {
    if (repeat) {
      const target = getNextLunarOccurrenceDate(item.lunar_month, item.lunar_day, !!item.lunar_leap);
      if (!target) {
        return { count_status: 'upcoming', days_left: 0, days_since: 0 };
      }
      const days_left = Math.max(0, Math.ceil((target - today) / DAY_MS));
      if (days_left === 0) {
        return { count_status: 'today', days_left: 0, days_since: 0 };
      }
      return { count_status: 'upcoming', days_left, days_since: 0 };
    }
    const thisOcc = getThisYearLunarSolarDate(item.lunar_month, item.lunar_day, !!item.lunar_leap);
    if (!thisOcc) {
      return { count_status: 'upcoming', days_left: 0, days_since: 0 };
    }
    if (thisOcc > today) {
      const days_left = Math.ceil((thisOcc - today) / DAY_MS);
      return { count_status: 'upcoming', days_left, days_since: 0 };
    }
    if (thisOcc.getTime() === today.getTime()) {
      return { count_status: 'today', days_left: 0, days_since: 0 };
    }
    const days_since = Math.ceil((today - thisOcc) / DAY_MS);
    return { count_status: 'passed', days_left: 0, days_since };
  }

  if (repeat) {
    const next = getNextSolarOccurrenceDate(item.date);
    if (!next) {
      return { count_status: 'upcoming', days_left: 0, days_since: 0 };
    }
    const days_left = Math.max(0, Math.ceil((next - today) / DAY_MS));
    if (days_left === 0) {
      return { count_status: 'today', days_left: 0, days_since: 0 };
    }
    return { count_status: 'upcoming', days_left, days_since: 0 };
  }

  const thisOcc = getThisYearSolarOccurrenceDate(item.date);
  if (!thisOcc) {
    return { count_status: 'upcoming', days_left: 0, days_since: 0 };
  }
  if (thisOcc > today) {
    const days_left = Math.ceil((thisOcc - today) / DAY_MS);
    return { count_status: 'upcoming', days_left, days_since: 0 };
  }
  if (thisOcc.getTime() === today.getTime()) {
    return { count_status: 'today', days_left: 0, days_since: 0 };
  }
  const days_since = Math.ceil((today - thisOcc) / DAY_MS);
  return { count_status: 'passed', days_left: 0, days_since };
}

const modalTitle = computed(() =>
  editingId.value != null ? '✨ 编辑纪念日' : '✨ 添加新纪念日'
);

const isOwnAnniversary = (item) => {
  const uid = currentUserId.value;
  if (uid == null || item.user_id == null) return false;
  return String(uid) === String(item.user_id);
};

onMounted(() => {
  if (!currentUserId.value) {
    router.push('/');
    return;
  }
  calculateMainAnniversary();
  loadAnniversaries();
});

const calculateMainAnniversary = () => {
  const startDate = new Date('2022-08-14'); 
  const now = new Date();
  const diffTime = Math.abs(now - startDate);
  daysCount.value = Math.floor(diffTime / (1000 * 60 * 60 * 24));
};

const loadAnniversaries = async () => {
  loading.value = true;
  try {
    const response = await fetch(`${APP_CONFIG.API_BASE}/anniversary.php?user_id=${currentUserId.value}`);
    const data = await response.json();
    if (data.success && data.data) {
      const rows = data.data.map((item) => {
        const m = buildAnniversaryMetrics(item);
        return {
          ...item,
          name: item.title,
          dateLabel: formatItemDateLabel(item),
          days_left: m.days_left,
          days_since: m.days_since,
          count_status: m.count_status
        };
      });
      rows.sort((a, b) => {
        const ap = a.count_status === 'passed';
        const bp = b.count_status === 'passed';
        if (ap !== bp) return ap ? 1 : -1;
        if (ap) {
          const d = a.days_since - b.days_since;
          if (d !== 0) return d;
        } else {
          const d = a.days_left - b.days_left;
          if (d !== 0) return d;
        }
        const ia = a.id != null ? Number(a.id) : 0;
        const ib = b.id != null ? Number(b.id) : 0;
        return ia - ib;
      });
      anniversaries.value = rows;
    }
  } catch (error) {
    console.error('Failed to load anniversaries:', error);
  } finally {
    loading.value = false;
  }
};

const openAddModal = () => {
  editingId.value = null;
  newAnniv.name = '';
  newAnniv.date = '';
  newAnniv.description = '';
  newAnniv.isLunar = false;
  newAnniv.repeatYearly = false;
  newAnniv.lunarYear = new Date().getFullYear();
  newAnniv.lunarMonth = 1;
  newAnniv.lunarDay = 1;
  newAnniv.lunarLeap = false;
  showAddModal.value = true;
};

const closeAddModal = () => {
  showAddModal.value = false;
  editingId.value = null;
};

const openEditModal = (item) => {
  editingId.value = item.id;
  newAnniv.name = item.title || item.name || '';
  newAnniv.description = item.description || '';
  newAnniv.isLunar = !!item.is_lunar;
  if (newAnniv.isLunar) {
    newAnniv.lunarYear = Number(item.lunar_year) || new Date().getFullYear();
    newAnniv.lunarMonth = Number(item.lunar_month) || 1;
    newAnniv.lunarDay = Number(item.lunar_day) || 1;
    newAnniv.lunarLeap = !!item.lunar_leap;
    const d = item.date;
    newAnniv.date =
      typeof d === 'string' && d.length >= 10 ? d.slice(0, 10) : d || '';
  } else {
    const d = item.date;
    newAnniv.date =
      typeof d === 'string' && d.length >= 10 ? d.slice(0, 10) : d || '';
  }
  newAnniv.repeatYearly = isRepeatYearlyItem(item);
  showAddModal.value = true;
};

const saveAnniversary = async () => {
  if (!newAnniv.name.trim()) {
    customAlert('请填写名称');
    return;
  }

  let payloadDate = '';
  let isLunar = false;
  let lunar_year = 0;
  let lunar_month = 0;
  let lunar_day = 0;
  let lunar_leap = false;

  if (!newAnniv.isLunar) {
    if (!newAnniv.date) {
      customAlert('请选择公历日期');
      return;
    }
    payloadDate = newAnniv.date;
  } else {
    const solar = lunarCalendar.lunar2solar(
      newAnniv.lunarYear,
      newAnniv.lunarMonth,
      newAnniv.lunarDay,
      newAnniv.lunarLeap
    );
    if (!solar) {
      customAlert('农历日期无效，请检查闰月、月份与日期是否匹配');
      return;
    }
    payloadDate = `${solar.cYear}-${pad2(solar.cMonth)}-${pad2(solar.cDay)}`;
    isLunar = true;
    lunar_year = newAnniv.lunarYear;
    lunar_month = newAnniv.lunarMonth;
    lunar_day = newAnniv.lunarDay;
    lunar_leap = newAnniv.lunarLeap;
  }

  const isUpdate = editingId.value != null;
  const payload = {
    action: isUpdate ? 'update' : 'add',
    user_id: parseInt(currentUserId.value, 10),
    title: newAnniv.name.trim(),
    date: payloadDate,
    description: newAnniv.description,
    is_lunar: isLunar,
    lunar_year,
    lunar_month,
    lunar_day,
    lunar_leap,
    repeat_yearly: newAnniv.repeatYearly
  };

  if (isUpdate) {
    payload.id = editingId.value;
  }

  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/anniversary.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    if (data.success) {
      customAlert(isUpdate ? '更新成功' : '添加成功');
      closeAddModal();
      newAnniv.name = '';
      newAnniv.date = '';
      newAnniv.description = '';
      newAnniv.isLunar = false;
      newAnniv.lunarYear = new Date().getFullYear();
      newAnniv.lunarMonth = 1;
      newAnniv.lunarDay = 1;
      newAnniv.lunarLeap = false;
      newAnniv.repeatYearly = false;
      loadAnniversaries();
    } else {
      customAlert(data.message || (isUpdate ? '更新失败' : '添加失败'));
    }
  } catch (error) {
    customAlert('网络错误');
  }
};

const deleteAnniv = async (id) => {
  if (!await customConfirm('确定删除这个纪念日吗？')) return;
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/anniversary.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'delete',
        id: id,
        user_id: parseInt(currentUserId.value)
      })
    });
    if (data.success) {
      loadAnniversaries();
    } else {
      customAlert(data.message || '删除失败');
    }
  } catch (error) {
    customAlert('网络错误');
  }
};

</script>

<style scoped>
.anniversary-container {
  min-height: 100vh;
  padding-top: 70px;
  padding-bottom: 90px;
}

.anniversary-main{
  animation: fadeInUp 0.7s;
}

/* Fancy Header Card */
.anniv-fancy {
  background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 50%, #fecfef 100%);
  box-shadow: 0 20px 60px rgba(255, 154, 158, 0.3), 0 8px 30px rgba(254, 207, 239, 0.2);
  border: 1px solid rgba(255, 255, 255, 0.3);
  border-radius: 28px;
  padding: 40px 30px 35px 30px;
  margin: 20px;
  text-align: center;
  position: relative;
  backdrop-filter: blur(10px);
  overflow: hidden;
}

:global(body.dark-theme) .anniv-fancy {
  background: linear-gradient(135deg, #2c3e50 0%, #34495e 50%, #2c3e50 100%);
  box-shadow: 0 20px 60px rgba(0,0,0,0.4), 0 8px 30px rgba(52, 73, 94, 0.3);
  border: 1px solid rgba(255,255,255,0.1);
}

.anniv-title {
  font-size: 18px;
  color: #ff6f91;
  font-weight: 600;
  margin-bottom: 15px;
  position: relative;
  z-index: 2;
}

:global(body.dark-theme) .anniv-title {
  color: #ffbfa8;
}

.anniv-days-row {
  margin: 15px 0;
  display: flex;
  justify-content: center;
  align-items: baseline;
  gap: 10px;
  position: relative;
  z-index: 2;
}

.anniv-days {
  font-size: 72px;
  font-weight: 900;
  background: linear-gradient(45deg, #ff6b8a, #ff3b30, #ff9a56);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  line-height: 1;
}

:global(body.dark-theme) .anniv-days {
  background: none;
  -webkit-text-fill-color: initial;
  color: #ffbfa8;
  text-shadow: 0 2px 12px rgba(34,34,34,0.6);
}

.anniv-days-label {
  font-size: 24px;
  color: #ff6f91;
  font-weight: 700;
  margin-bottom: 8px;
}

:global(body.dark-theme) .anniv-days-label {
  color: #ffbfa8;
}

.anniv-subtitle {
  font-size: 14px;
  color: #ff8fa3;
  font-style: italic;
  position: relative;
  z-index: 2;
}

:global(body.dark-theme) .anniv-subtitle {
  color: #e0e0e0;
}

/* Modern Add Button */
.add-btn-modern {
  width: 90%;
  margin: 0 auto;
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 8px;
  padding: 16px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border: none;
  border-radius: 24px;
  font-size: 16px;
  font-weight: 600;
  box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
  cursor: pointer;
  transition: transform 0.2s;
}

.add-btn-modern:active {
  transform: scale(0.96);
}

/* Public Anniversaries Section */
.public-anniversaries-section {
  margin-top: 30px;
}

.public-anniversaries-section h2 {
  font-size: 22px;
  color: #ff6f91;
  margin-left: 20px;
  font-weight: bold;
}

:global(body.dark-theme) .public-anniversaries-section h2 {
  color: #ffbfa8;
}

/* Anniversary Item Card */
.anniv-card {
  background: var(--bg-color-card, #fff);
  border-radius: 20px;
  padding: 24px 26px;
  margin: 15px 20px;
  box-shadow: 0 8px 32px var(--shadow-color, rgba(0,0,0,0.05));
  border: 1px solid var(--border-color, transparent);
  color: var(--text-color-light, #333);
}

:global(body.dark-theme) .anniv-card {
  background: var(--bg-color-card);
  box-shadow: 0 8px 32px var(--shadow-color, rgba(0,0,0,0.4));
  border: 1px solid var(--border-color, #555);
}

.anniv-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.anniv-card-title {
  font-size: 20px;
  font-weight: bold;
  color: #ff6f91;
}

:global(body.dark-theme) .anniv-card-title {
  color: #ffbfa8;
}

.anniv-card-date {
  font-size: 15px;
  color: #ff6f91;
  font-weight: 500;
}

:global(body.dark-theme) .anniv-card-date {
  color: #ffbfa8;
}

.anniv-card-desc {
  margin-top: 8px;
  color: var(--text-color-medium, #666);
  font-size: 15px;
}

.anniv-card-countdown {
  margin-top: 15px;
  color: var(--text-color-medium, #666);
  font-size: 16px;
  font-weight: 600;
}

.anniv-card-countdown.is-passed .countdown-days {
  color: #9e9e9e;
}

:global(body.dark-theme) .anniv-card-countdown.is-passed .countdown-days {
  color: #b0b0b0;
}

.countdown-today {
  margin: 0;
}

.anniv-repeat-row {
  margin-top: 4px;
}

.anniv-repeat-label {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  font-weight: normal;
  line-height: 1.45;
  cursor: pointer;
}

.countdown-days {
  font-size: 20px;
  color: #ff4081;
  font-weight: bold;
  margin: 0 4px;
}

:global(body.dark-theme) .countdown-days {
  color: #ffbfa8;
}

.anniv-card-actions {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}

.anniv-card-actions .anniv-edit-btn,
.anniv-card-actions .anniv-delete-btn {
  background: transparent;
  border: none;
  font-size: 18px;
  line-height: 1;
  cursor: pointer;
  padding: 4px 6px;
  border-radius: 8px;
  opacity: 0.85;
  color: var(--text-color-medium, #666);
  transition: opacity 0.2s, background 0.2s;
}

.anniv-card-actions .anniv-edit-btn:hover,
.anniv-card-actions .anniv-delete-btn:hover {
  opacity: 1;
  background: var(--shadow-color, rgba(0, 0, 0, 0.06));
}

:global(body.dark-theme) .anniv-card-actions .anniv-edit-btn,
:global(body.dark-theme) .anniv-card-actions .anniv-delete-btn {
  color: var(--text-color-light, #e0e0e0);
  opacity: 0.95;
}

:global(body.dark-theme) .anniv-card-actions .anniv-edit-btn:hover,
:global(body.dark-theme) .anniv-card-actions .anniv-delete-btn:hover {
  background: rgba(255, 255, 255, 0.08);
}

/* Modal */
.modal {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: var(--modal-shadow, rgba(0,0,0,0.6));
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: #fff;
  color: #222;
  border-radius: 20px;
  width: 90%;
  max-width: 400px;
  overflow: hidden;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
}

.anniv-modal-body {
  padding: 18px 16px 20px;
  box-sizing: border-box;
}

.modal-field-group {
  margin-bottom: 18px;
}

.modal-field-group--lunar .modal-field-label {
  margin-bottom: 10px;
}

.modal-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 16px 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h2 {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
}

.modal-close {
  background: transparent;
  border: none;
  color: white;
  font-size: 24px;
  cursor: pointer;
}

/* 公历 / 农历：分段按钮，无原生 radio，避免圆点样式不一致 */
.date-type-segment {
  display: flex;
  gap: 0;
  padding: 4px;
  border-radius: 14px;
  background: #eceef2;
  box-sizing: border-box;
}

.date-seg-btn {
  flex: 1;
  min-width: 0;
  margin: 0;
  padding: 11px 14px;
  border: none;
  border-radius: 11px;
  background: transparent;
  color: #555;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s, color 0.2s, box-shadow 0.2s;
  -webkit-tap-highlight-color: transparent;
}

.date-seg-btn.active {
  background: #fff;
  color: #5a67d8;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.lunar-inputs-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
  align-items: stretch;
  margin-bottom: 12px;
}

.lunar-select-wrap {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.lunar-select-hint {
  font-size: 11px;
  color: #888;
  text-align: center;
  line-height: 1.2;
}

.lunar-select {
  width: 100%;
  min-width: 0;
  padding: 10px 6px;
  border: 1px solid #ddd;
  border-radius: 10px;
  font-size: 13px;
  background: #fff;
  color: #222;
  text-align: center;
  box-sizing: border-box;
  cursor: pointer;
}

.lunar-leap-row {
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: flex-start;
  gap: 10px;
  width: 100%;
  margin-bottom: 12px;
  padding: 10px 12px;
  border-radius: 10px;
  background: rgba(0, 0, 0, 0.04);
  box-sizing: border-box;
}

.lunar-leap-checkbox {
  width: 18px;
  height: 18px;
  margin: 0;
  flex-shrink: 0;
  accent-color: #667eea;
  cursor: pointer;
}

.lunar-leap-text {
  flex: 1;
  margin: 0;
  font-size: 14px;
  color: #555;
  line-height: 1.4;
  cursor: pointer;
  text-align: left;
}

.lunar-preview-box {
  margin-bottom: 16px;
  padding: 10px 12px;
  border-radius: 10px;
  background: #f5f7fa;
  border: 1px solid #e8ecf0;
}

.lunar-preview-line {
  font-size: 14px;
  color: #444;
  line-height: 1.45;
}

.lunar-preview-line.strong {
  font-weight: 600;
  color: #333;
  margin-bottom: 4px;
}

.modal-field-label {
  display: block;
  margin-bottom: 8px;
  font-size: 14px;
  color: #555;
  font-weight: 500;
}

.modal-input-full,
.modal-textarea-full {
  width: 100%;
  padding: 11px 12px;
  margin: 0;
  border: 1px solid #ddd;
  border-radius: 10px;
  background: #fff;
  color: #222;
  box-sizing: border-box;
  font-size: 15px;
}

.modal-textarea-full {
  resize: none;
  min-height: 88px;
  line-height: 1.45;
  font-family: inherit;
}

.modal-input-full::placeholder,
.modal-textarea-full::placeholder {
  color: #888;
}

.modal-input-full:focus,
.modal-textarea-full:focus {
  border-color: #007aff;
  outline: none;
}

.modal-actions {
  display: flex;
  gap: 12px;
  margin-top: 22px;
  padding-top: 18px;
  border-top: 1px solid rgba(0, 0, 0, 0.08);
}

.btn-save, .btn-cancel {
  flex: 1;
  padding: 12px;
  border: none;
  border-radius: 12px;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  color: white;
}

.btn-save { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.btn-cancel { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
</style>

<style>
/* 纪念日弹窗暗黑主题：非 scoped，确保 body.dark-theme 选择器生效 */
body.dark-theme .anniversary-modal-root .modal-content {
  background: #1e2228;
  color: #e8eaed;
  border: 1px solid #3a4048;
  box-shadow: 0 12px 48px rgba(0, 0, 0, 0.55);
}

body.dark-theme .anniversary-modal-root .modal-body {
  background: #1e2228;
}

body.dark-theme .anniversary-modal-root .date-type-segment {
  background: #2a3038;
}

body.dark-theme .anniversary-modal-root .date-seg-btn {
  color: #c4c7ce;
}

body.dark-theme .anniversary-modal-root .date-seg-btn.active {
  background: #323842;
  color: #b8c7ff;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.35);
}

body.dark-theme .anniversary-modal-root .lunar-select-hint {
  color: #8a9099;
}

body.dark-theme .anniversary-modal-root .lunar-leap-row {
  background: rgba(255, 255, 255, 0.06);
}

body.dark-theme .anniversary-modal-root .lunar-leap-text {
  color: #c4c7ce;
}

body.dark-theme .anniversary-modal-root .modal-actions {
  border-top-color: rgba(255, 255, 255, 0.1);
}

body.dark-theme .anniversary-modal-root .modal-header {
  background: linear-gradient(135deg, #3d4f8c 0%, #5c3d7a 100%);
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.35);
}

body.dark-theme .anniversary-modal-root .modal-field-label {
  color: #c4c7ce;
}

body.dark-theme .anniversary-modal-root .modal-input-full,
body.dark-theme .anniversary-modal-root .modal-textarea-full {
  background: #2a3038;
  color: #e8eaed;
  border-color: #454d58;
}

body.dark-theme .anniversary-modal-root .modal-input-full::placeholder,
body.dark-theme .anniversary-modal-root .modal-textarea-full::placeholder {
  color: #8a9099;
}

body.dark-theme .anniversary-modal-root .modal-input-full:focus,
body.dark-theme .anniversary-modal-root .modal-textarea-full:focus {
  border-color: #5b8cff;
  background: #323842;
  outline: none;
}

body.dark-theme .anniversary-modal-root .modal-content input[type='date'] {
  color-scheme: dark;
}

body.dark-theme .anniversary-modal-root .lunar-select {
  background: #2a3038;
  color: #e8eaed;
  border-color: #454d58;
}

body.dark-theme .anniversary-modal-root .lunar-preview-box {
  background: #2a3038;
  border-color: #454d58;
}

body.dark-theme .anniversary-modal-root .lunar-preview-line {
  color: #c4c7ce;
}

body.dark-theme .anniversary-modal-root .lunar-preview-line.strong {
  color: #e8eaed;
}

body.dark-theme .anniversary-modal-root .btn-save {
  background: linear-gradient(135deg, #2d6fa3 0%, #1a7a8c 100%);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.35);
}

body.dark-theme .anniversary-modal-root .btn-cancel {
  background: linear-gradient(135deg, #6b4d7a 0%, #8c3d55 100%);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.35);
}
</style>
