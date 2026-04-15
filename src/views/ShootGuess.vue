<template>
  <div class="shoot-container">
    <header class="gomoku-header">
      <button id="shootBackBtn" class="gomoku-back" @click="goBack">←</button>
      <span class="gomoku-title">射履</span>
    </header>

    <div id="shootLobby" class="shoot-lobby" v-show="!inRoom">
      <div class="gomoku-room-list">
        <div class="gomoku-room-list-header">
          <span>房间列表</span>
          <div style="display:flex;gap:10px;">
            <button class="gomoku-join-btn" style="padding:8px 16px;" @click="createRoom">＋ 创建房间</button>
            <button class="gomoku-join-btn" style="padding:8px 16px;background:linear-gradient(90deg,#7aa2f7,#4f8cff);" @click="loadRooms">刷新</button>
          </div>
        </div>
        <div id="shootRoomsList">
            <div v-if="loadingRooms" class="gomoku-empty loading">加载中...</div>
            <div v-else-if="rooms.length === 0" class="gomoku-empty loading">暂无房间，快来创建吧！</div>
            <div v-else v-for="room in rooms" :key="room.id" class="gomoku-room-item">
                <span class="gomoku-room-name">{{ room.room_name }}</span>
                <button class="gomoku-join-btn" @click="joinRoom(room.room_code)">
                    {{ room.player1_id && room.player2_id ? '进入' : '加入' }}
                </button>
            </div>
        </div>
      </div>
    </div>

    <div id="shootGame" class="shoot-game" v-show="inRoom">
      <div class="shoot-info">
        <div class="shoot-player" id="shootP1"><span class="name">{{ p1Name }}</span><span class="score">{{ p1Score }}</span></div>
        <div class="vs">VS</div>
        <div class="shoot-player" id="shootP2"><span class="name">{{ p2Name }}</span><span class="score">{{ p2Score }}</span></div>
      </div>
      <div class="shoot-round">
        <div>回合 <span>{{ round }}</span>/5 · 类别 <span>{{ category }}</span> · 剩余机会 <span>{{ chances }}</span> · 倒计时 <span>{{ timeLeft }}</span>s</div>
        <div class="shoot-timer"><div class="shoot-timer-bar" :style="{ width: progressWidth + '%' }"></div></div>
      </div>
      
      <div class="shoot-panels">
        <div class="shoot-left">
          <div class="shoot-panel-title">对话</div>
          <div id="shootMessages" class="shoot-messages" ref="messagesRef">
             <div v-for="(m, idx) in messages" :key="idx" class="shoot-message" :class="{ system: m.type === 'system' }">
                 <b v-if="m.role==='asker'">履：</b>
                 <b v-else-if="m.role==='shooter'">射：</b>
                 <span v-html="m.text"></span>
             </div>
          </div>
          <div id="askArea" class="shoot-input" v-show="showAskArea">
            <input v-model="askText" type="text" :placeholder="askPlaceholder" maxlength="60" :disabled="disableAsk">
            <button class="shoot-btn primary" @click="sendQuestion" :disabled="disableAsk">发送</button>
          </div>
        </div>
        
        <div class="shoot-right">
          <div id="setterArea" class="shoot-setter-bar" v-show="showSetterArea">
            <div class="setter-inner">
              <input v-model="setterCategory" class="setter-input" type="text" placeholder="输入类别（自定义）" maxlength="10">
              <input v-model="setterWord" class="setter-input" type="text" placeholder="输入谜底" maxlength="12">
              <button class="shoot-btn primary setter-btn" @click="startRound">开始！</button>
            </div>
          </div>
          <div id="judgeArea" class="shoot-panel" v-show="showJudgeArea">
            <div class="shoot-panel-title">回答</div>
            <div class="shoot-judge">
              <button class="shoot-btn" @click="sendJudge('是')">是</button>
              <button class="shoot-btn" @click="sendJudge('否')">否</button>
              <button class="shoot-btn" @click="sendJudge('接近了')">接近了</button>
              <button class="shoot-btn" @click="sendJudge('差远了')">差远了</button>
              <button class="shoot-btn danger" @click="sendJudge('答案正确')">答案正确</button>
            </div>
          </div>
        </div>
      </div>
      <div class="shoot-controls">
        <button class="shoot-btn danger" v-show="showEndBtn" @click="endGame">结束比赛</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, computed, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import { APP_CONFIG, commonFetch } from '../utils/config';
import { customAlert, customConfirm } from '../utils/modal';

const router = useRouter();
const userId = ref(localStorage.getItem('user_id'));
const username = ref(localStorage.getItem('username') || '');

const inRoom = ref(false);
const rooms = ref([]);
const loadingRooms = ref(false);

const currentRoomCode = ref('');
const isShooter = ref(false);

const p1Name = ref('P1');
const p2Name = ref('P2');
const p1Score = ref(0);
const p2Score = ref(0);
const round = ref(1);
const category = ref('—');
const chances = ref(15);
const timeLeft = ref(60);

const messages = ref([]);
const messagesRef = ref(null);

const askText = ref('');
const setterCategory = ref('');
const setterWord = ref('');

const gameStatus = ref('');
const turnDeadline = ref(null);

let pollTimer = null;
let countdownTimer = null;

const cachedNames = {};
const getPlayerName = (id, fallback, defaultName) => {
  if (!id) return defaultName;
  if (String(id) === String(userId.value)) return username.value;
  if (fallback && fallback !== 'P1' && fallback !== 'P2' && fallback !== '玩家1' && fallback !== '等待玩家') {
    cachedNames[id] = fallback;
    return fallback;
  }
  if (cachedNames[id]) {
    return cachedNames[id] === 'fetching' ? defaultName : cachedNames[id];
  }
  cachedNames[id] = 'fetching';
  commonFetch(`${APP_CONFIG.API_BASE}/profile.php?user_id=${id}`).then(p => {
    if (p.success && p.user && p.user.username) {
      cachedNames[id] = p.user.username;
    } else {
      cachedNames[id] = defaultName;
    }
  }).catch(() => { cachedNames[id] = defaultName; });
  return defaultName;
};

onMounted(() => {
  if (!userId.value) {
    customAlert('请先登录！');
    router.push('/');
    return;
  }
  loadRooms();
});

onBeforeUnmount(() => {
  stopPolling();
  stopLocalCountdown();
});

const goBack = () => {
  if (inRoom.value) {
      exitRoom();
  } else {
      router.back();
  }
};

const loadRooms = async () => {
  loadingRooms.value = true;
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/shoot/list_rooms.php`);
    if (data.success && data.rooms) {
      rooms.value = data.rooms;
    } else {
      rooms.value = [];
    }
  } catch(e) {
      console.error(e);
  } finally {
      loadingRooms.value = false;
  }
};

const createRoom = async () => {
  const name = `${username.value}的射履房`;
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/shoot/create_room.php`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ user_id: userId.value, room_name: name, nickname: username.value })
    });
    if (data.success) {
      enterGame(data.room_code);
    } else {
      customAlert(data.message || '创建失败');
    }
  } catch(e) {
      customAlert('网络错误');
  }
};

const joinRoom = async (code) => {
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/shoot/join_room.php`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ user_id: userId.value, room_code: code, nickname: username.value })
    });
    if (data.success) {
      enterGame(code);
    } else {
      customAlert(data.message || '加入失败');
    }
  } catch(e) {
      customAlert('网络错误');
  }
};

const enterGame = (code) => {
  currentRoomCode.value = code;
  inRoom.value = true;
  messages.value = [];
  startPolling();
};

const exitRoom = () => {
    inRoom.value = false;
    currentRoomCode.value = '';
    stopPolling();
    stopLocalCountdown();
    loadRooms();
}

const startPolling = () => {
  stopPolling();
  poll();
  pollTimer = setInterval(poll, 1000);
};

const stopPolling = () => {
  if (pollTimer) clearInterval(pollTimer);
  pollTimer = null;
};

const poll = async () => {
  if (!currentRoomCode.value) return;
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/shoot/room_status.php?room_code=${currentRoomCode.value}&user_id=${userId.value}`);
    if (!data.success) return;
    
    const room = data.room;
    isShooter.value = String(room.shooter_id) === String(userId.value);
    gameStatus.value = room.game_status;
    turnDeadline.value = room.turn_deadline;
    
    p1Name.value = getPlayerName(room.player1_id, room.player1_name, 'P1');
    p2Name.value = getPlayerName(room.player2_id, room.player2_name, 'P2');
    p1Score.value = room.player1_score || 0;
    p2Score.value = room.player2_score || 0;
    round.value = room.current_round || 1;
    category.value = room.category || '—';
    chances.value = room.chances_left || 15;
    
    if (typeof room.time_left === 'number') {
      startLocalCountdown(room.time_left);
    } else {
      stopLocalCountdown();
      timeLeft.value = 60;
    }
    
    if (room.messages && room.messages.length > messages.value.length) {
        messages.value = room.messages;
        nextTick(() => {
            if (messagesRef.value) messagesRef.value.scrollTop = messagesRef.value.scrollHeight;
        });
    }
    
    if (room.game_status === 'finished') {
      customAlert(`比赛结束\n${room.player1_name || 'P1'}：${room.player1_score || 0} 分\n${room.player2_name || 'P2'}：${room.player2_score || 0} 分`);
      try {
          await commonFetch(`${APP_CONFIG.API_BASE}/shoot/cleanup_room.php`, {
              method: 'POST', headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ room_code: currentRoomCode.value })
          });
      } catch(e) {}
      exitRoom();
    }
  } catch(e) {}
};

const startLocalCountdown = (seconds) => {
  stopLocalCountdown();
  let left = Math.max(0, parseInt(seconds, 10) || 0);
  timeLeft.value = left;
  countdownTimer = setInterval(() => {
    left = Math.max(0, left - 1);
    timeLeft.value = left;
    if (left <= 0) stopLocalCountdown();
  }, 1000);
};

const stopLocalCountdown = () => {
  if (countdownTimer) clearInterval(countdownTimer);
  countdownTimer = null;
};

const progressWidth = computed(() => {
  return Math.max(0, Math.min(100, (timeLeft.value / 60) * 100));
});

const awaitingJudge = computed(() => gameStatus.value === 'asking' && !turnDeadline.value);
const showSetterArea = computed(() => gameStatus.value === 'waiting_set' && isShooter.value);
const showJudgeArea = computed(() => gameStatus.value === 'asking' && isShooter.value && awaitingJudge.value);
const showAskArea = computed(() => gameStatus.value === 'asking' && !isShooter.value);
const showEndBtn = computed(() => gameStatus.value !== 'finished');

const disableAsk = computed(() => isShooter.value || awaitingJudge.value || gameStatus.value !== 'asking');
const askPlaceholder = computed(() => {
  if (isShooter.value) return '射者回答阶段，此处不可用';
  if (awaitingJudge.value) return '等待射者回答，请稍候...';
  if (gameStatus.value !== 'asking') return '当前不可提问';
  return '履者提问或直接输入答案进行猜测...';
});

const startRound = async () => {
  const w = setterWord.value.trim();
  const c = setterCategory.value.trim();
  if (!w) { customAlert('请填写谜底'); return; }
  if (!c) { customAlert('请填写类别'); return; }
  
  try {
      const data = await commonFetch(`${APP_CONFIG.API_BASE}/shoot/start_round.php`, {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ user_id: userId.value, room_code: currentRoomCode.value, word: w, category: c })
      });
      if (!data.success) customAlert(data.message || '开始失败');
  } catch(e) {}
};

const sendQuestion = async () => {
  const t = askText.value.trim();
  if (!t) return;
  try {
      const data = await commonFetch(`${APP_CONFIG.API_BASE}/shoot/send_question.php`, {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ user_id: userId.value, room_code: currentRoomCode.value, text: t })
      });
      if (data.success) askText.value = '';
  } catch(e) {}
};

const sendJudge = async (judgeText) => {
  try {
      const data = await commonFetch(`${APP_CONFIG.API_BASE}/shoot/send_judge.php`, {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ user_id: userId.value, room_code: currentRoomCode.value, judge: judgeText })
      });
      if (!data.success) customAlert(data.message || '判定失败');
  } catch(e) {}
};

const endGame = async () => {
  if (!await customConfirm('确定要结束当前比赛吗？')) return;
  try {
      await commonFetch(`${APP_CONFIG.API_BASE}/shoot/end_game.php`, {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ user_id: userId.value, room_code: currentRoomCode.value })
      });
  } catch(e) {}
};
</script>

<style scoped>
.shoot-container {
  min-height: 100vh;
  background: var(--bg-color-light, #f6f8fa);
  display: flex;
  flex-direction: column;
}

.gomoku-header {
  background: var(--header-bg, #ffffff);
  display: flex; align-items: center; padding: 14px 16px;
  box-shadow: 0 2px 12px var(--shadow-color, rgba(0,0,0,0.08));
}

.gomoku-back { background: transparent; border: none; font-size: 20px; font-weight: bold; cursor: pointer; color: var(--nav-btn-active-color, #4f8cff); }
.gomoku-title { flex: 1; text-align: center; font-size: 20px; font-weight: bold; color: var(--text-color-light, #333); }

.shoot-lobby { padding: 20px; max-width: 600px; margin: 0 auto; width: 100%; box-sizing: border-box; }
.gomoku-room-list { background: var(--bg-color-card, #fff); border-radius: 12px; padding: 20px; box-shadow: 0 4px 12px var(--shadow-color, rgba(0,0,0,0.05)); }

.gomoku-room-list-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; font-weight: bold; color: var(--text-color-light, #333); }

.gomoku-join-btn { background: var(--nav-btn-active-color, #4f8cff); color: white; border: none; padding: 8px 16px; border-radius: 20px; cursor: pointer; }
.gomoku-join-btn:disabled { background: var(--border-color, #ccc); cursor: not-allowed; }

.gomoku-room-item { display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid var(--border-color, #eee); color: var(--text-color-light, #333); }

.shoot-game { flex: 1; display: flex; flex-direction: column; padding: 10px; max-width: 800px; margin: 0 auto; width: 100%; box-sizing: border-box; }
.shoot-info { display: flex; justify-content: space-between; align-items: center; padding: 10px 20px; background: var(--bg-color-card, #fff); border-radius: 12px; box-shadow: 0 2px 8px var(--shadow-color, rgba(0,0,0,0.05)); margin-bottom: 10px; color: var(--text-color-light, #333); }
.shoot-player { display: flex; flex-direction: column; align-items: center; }
.shoot-player .name { font-weight: bold; font-size: 16px; }
.shoot-player .score { font-size: 24px; color: var(--nav-btn-active-color, #4f8cff); font-weight: bold; }
.vs { font-size: 20px; font-weight: bold; color: var(--text-color-medium, #999); font-style: italic; }

.shoot-round { background: var(--bg-color-card, #fff); border-radius: 12px; padding: 10px 20px; margin-bottom: 10px; font-size: 14px; color: var(--text-color-medium, #666); text-align: center; box-shadow: 0 2px 8px var(--shadow-color, rgba(0,0,0,0.05)); }
.shoot-round span { font-weight: bold; color: var(--text-color-light, #333); }

.shoot-timer { height: 6px; background: var(--bg-color-light, #eee); border-radius: 3px; margin-top: 8px; overflow: hidden; }
.shoot-timer-bar { height: 100%; background: linear-gradient(90deg, #4f8cff, #ff6b9d); transition: width 1s linear; }

.shoot-panels { display: flex; flex-direction: column; gap: 10px; flex: 1; }
@media(min-width: 600px) { .shoot-panels { flex-direction: row; } .shoot-left { flex: 2; } .shoot-right { flex: 1; } }

.shoot-left, .shoot-right { display: flex; flex-direction: column; gap: 10px; }

.shoot-panel-title { font-weight: bold; margin-bottom: 8px; color: var(--text-color-light, #333); }

.shoot-messages { flex: 1; background: var(--bg-color-card, #fff); border-radius: 12px; padding: 10px; overflow-y: auto; min-height: 200px; box-shadow: 0 2px 8px var(--shadow-color, rgba(0,0,0,0.05)); }
.shoot-message { margin-bottom: 8px; padding: 8px 12px; border-radius: 8px; background: var(--bg-color-light, #f0f6ff); color: var(--text-color-light, #333); font-size: 15px; }
.shoot-message.system { background: transparent; text-align: center; color: var(--text-color-medium, #999); font-size: 13px; }

.shoot-input, .shoot-setter-bar { display: flex; gap: 10px; }
.shoot-input input, .setter-input { flex: 1; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color, #ddd); font-size: 15px; background: var(--bg-color-card, #fff); color: var(--text-color-light, #333); }

.shoot-btn { padding: 10px 16px; border-radius: 8px; border: none; font-weight: bold; cursor: pointer; color: white; background: #666; }
.shoot-btn.primary { background: var(--nav-btn-active-color, #4f8cff); }
.shoot-btn.danger { background: var(--text-color-danger, #ff4d4d); }
.shoot-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.shoot-panel { background: var(--bg-color-card, #fff); border-radius: 12px; padding: 15px; box-shadow: 0 2px 8px var(--shadow-color, rgba(0,0,0,0.05)); }

.shoot-judge { display: flex; flex-wrap: wrap; gap: 8px; }
.shoot-judge .shoot-btn { flex: 1; min-width: 40%; }

.shoot-controls { text-align: center; margin-top: 10px; }

.setter-inner { display: flex; flex-wrap: wrap; gap: 8px; background: var(--bg-color-card, #fff); padding: 10px; border-radius: 12px; }
</style>
