<template>
  <div class="gomoku-page-wrapper">
    <AppHeader title="在线五子棋" :showBack="true" :customBack="goBack" />
    <div class="gomoku-main" style="margin-top: 70px;">
        <div id="gomokuRoomList" class="gomoku-room-list" v-show="!inRoom">
            <div class="gomoku-room-list-header">
                <span>房间列表</span>
                <button id="createRoomBtn" class="gomoku-join-btn" @click="createRoom">+ 创建房间</button>
            </div>
            <div id="roomListContainer" class="room-list-container">
                <LoadingSpinner v-if="loadingRooms" text="加载房间..." />
                <div v-else-if="rooms.length === 0" class="gomoku-empty loading">暂无房间，快来创建吧！</div>
                <div v-else v-for="room in rooms" :key="room.id" class="gomoku-room-item">
                    <span class="gomoku-room-name">{{ room.ownerName }}的房间</span>
                    <button class="gomoku-join-btn" :disabled="room.isFull && !room.isMine" @click="joinRoom(room)">
                        {{ room.isMine ? '进入' : (room.isFull ? '已满' : '加入') }}
                    </button>
                </div>
            </div>
        </div>
        
        <div id="gomokuGameArea" class="gomoku-game-area" v-show="inRoom">
            <div class="gomoku-game-header">
                <button class="gomoku-exit-btn" id="gomokuExitBtn" @click="exitRoom">← 返回房间列表</button>
                <div class="gomoku-players">
                    <span id="playerBlack" class="gomoku-player black" :class="{active: currentTurn === 1}">○ {{ player1Name || '等待玩家' }}</span>
                    <span class="vs">VS</span>
                    <span id="playerWhite" class="gomoku-player white" :class="{active: currentTurn === 2}">● {{ player2Name || '等待玩家' }}</span>
                </div>
                <div id="gomokuTurnInfo" class="gomoku-turn-info">当前回合：{{ currentTurnName }}</div>
            </div>
            <div class="gomoku-board-wrapper">
                <div id="gomokuBoard" class="gomoku-board">
                  <div v-for="i in 15" :key="'row-'+(i-1)" style="display:contents;">
                    <div v-for="j in 15" :key="'col-'+(i-1)+'-'+(j-1)" 
                         class="gomoku-cell" 
                         @click="handleCellClick(i-1, j-1)">
                      <div v-if="board[i-1][j-1] === 1" class="gomoku-stone black"></div>
                      <div v-else-if="board[i-1][j-1] === 2" class="gomoku-stone white"></div>
                      <div v-else-if="previewX === i-1 && previewY === j-1" class="gomoku-stone shadow" :class="{black: myColor === 1, white: myColor === 2}"></div>
                    </div>
                  </div>
                </div>
            </div>
            <div id="gomokuGameMsg" class="gomoku-game-msg">{{ gameMsg }}</div>
        </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from 'vue';
import { useRouter } from 'vue-router';
import { APP_CONFIG, commonFetch } from '../utils/config';
import { customAlert, customConfirm } from '../utils/modal';

const router = useRouter();
const userId = ref(localStorage.getItem('user_id'));
const username = ref(localStorage.getItem('username') || '');

const inRoom = ref(false);
const rooms = ref([]);
const loadingRooms = ref(true);

// Game state
const currentRoomCode = ref('');
const player1Name = ref('');
const player2Name = ref('');
const currentTurn = ref(0);
const board = ref(Array(15).fill().map(() => Array(15).fill(0)));
const myColor = ref(null); // 1 = black, 2 = white
const previewX = ref(null);
const previewY = ref(null);
const gameMsg = ref('');
const gameOverShown = ref(false);

const cachedNames = {};
const getPlayerName = (id, fallback, defaultName) => {
  if (!id) return defaultName;
  if (String(id) === String(userId.value)) return username.value;
  if (fallback && fallback !== '玩家1' && fallback !== '玩家2') {
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

let roomStatusTimer = null;
let rematchPollingTimer = null;
let rematchWaiting = false;

onMounted(() => {
  if (!userId.value) {
    customAlert('请先登录！');
    router.push('/');
    return;
  }
  loadRoomList();
});

onBeforeUnmount(() => {
  if (roomStatusTimer) clearInterval(roomStatusTimer);
  if (rematchPollingTimer) clearInterval(rematchPollingTimer);
});

const goBack = () => {
  router.back();
};

const currentTurnName = computed(() => {
  if (currentTurn.value === 1) return player1Name.value || '等待玩家';
  if (currentTurn.value === 2) return player2Name.value || '等待玩家';
  return '-';
});

const loadRoomList = async () => {
  loadingRooms.value = true;
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/gomoku/gomoku_list_rooms.php`);
    if (data.success && data.rooms) {
      const roomData = [];
      for (const r of data.rooms) {
        const isP1 = String(r.player1_id) === String(userId.value);
        const isP2 = String(r.player2_id) === String(userId.value);
        let ownerName = '房主';
        if (r.player1_id) {
          try {
            const pData = await commonFetch(`${APP_CONFIG.API_BASE}/profile.php?user_id=${r.player1_id}`);
            ownerName = pData.user?.username || '房主';
          } catch(e){}
        }
        roomData.push({
          ...r,
          isMine: isP1 || isP2,
          isFull: r.player1_id && r.player2_id && !isP1 && !isP2,
          ownerName
        });
      }
      rooms.value = roomData;
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
  if (!await customConfirm('确定要创建房间吗？')) return;
  const roomName = `${username.value}的房间`;
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/gomoku/gomoku_create_room.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ nickname: username.value, user_id: userId.value, room_name: roomName })
    });
    if (data.success) {
      enterRoom(data.room_code);
    } else {
      customAlert(data.message || '创建失败');
    }
  } catch(e) {
    customAlert('网络错误');
  }
};

const joinRoom = async (room) => {
  if (room.isMine) {
    enterRoom(room.room_code);
  } else {
    if (!await customConfirm('确定要加入该房间吗？')) return;
    try {
      const data = await commonFetch(`${APP_CONFIG.API_BASE}/gomoku/gomoku_join_room.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ room_code: room.room_code, nickname: username.value, user_id: userId.value })
      });
      if (data.success) {
        enterRoom(room.room_code);
      } else {
        customAlert(data.message || '加入失败');
      }
    } catch(e) {
      customAlert('网络错误');
    }
  }
};

const enterRoom = (roomCode) => {
  currentRoomCode.value = roomCode;
  inRoom.value = true;
  gameOverShown.value = false;
  previewX.value = null;
  previewY.value = null;
  gameMsg.value = '';
  
  if (roomStatusTimer) clearInterval(roomStatusTimer);
  fetchAndRenderRoom();
  roomStatusTimer = setInterval(fetchAndRenderRoom, 1500);
  
  if (rematchPollingTimer) clearInterval(rematchPollingTimer);
  pollRematchInvite();
};

const exitRoom = () => {
  inRoom.value = false;
  if (roomStatusTimer) clearInterval(roomStatusTimer);
  if (rematchPollingTimer) clearInterval(rematchPollingTimer);
  currentRoomCode.value = '';
  gameOverShown.value = false;
  rematchWaiting = false;
  loadRoomList();
};

const fetchAndRenderRoom = async () => {
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/gomoku/gomoku_room_status.php?room_code=${currentRoomCode.value}`);
    if (!data.success) {
      gameMsg.value = data.message || '获取信息失败';
      return;
    }
    const room = data.room;
    
    // Auto join if needed
    if (String(room.player1_id) !== String(userId.value) && (!room.player2_id || String(room.player2_id) !== String(userId.value))) {
      await commonFetch(`${APP_CONFIG.API_BASE}/gomoku/gomoku_join_room.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ room_code: currentRoomCode.value, nickname: username.value, user_id: userId.value })
      });
    }
    
    player1Name.value = getPlayerName(room.player1_id, room.player1_name, '玩家1');
    player2Name.value = getPlayerName(room.player2_id, room.player2_name, '等待玩家');
    currentTurn.value = room.current_turn;
    
    if (String(room.player1_id) === String(userId.value)) myColor.value = 1;
    else if (String(room.player2_id) === String(userId.value)) myColor.value = 2;
    else myColor.value = null;
    
    if (room.current_turn !== myColor.value) {
      previewX.value = null;
      previewY.value = null;
    }
    
    if (room.board_state) {
      try { board.value = JSON.parse(room.board_state); } catch(e){}
    } else {
      board.value = Array(15).fill().map(() => Array(15).fill(0));
    }
    
    if (!gameOverShown.value && room.winner !== null && room.winner !== undefined && room.winner !== 0) {
      let msg = '';
      if (room.winner == -1) msg = '平局！';
      else if (String(room.winner) === String(userId.value)) msg = '你赢了！🎉';
      else msg = '你输了！';
      
      gameOverShown.value = true;
      if (await customConfirm(`对局结束：${msg}\n\n是否再来一局？`)) {
        initiateRematch(room);
      } else {
        exitRoom();
      }
    }
    
  } catch(e) {
    console.error(e);
  }
};

const handleCellClick = async (i, j) => {
  if (!myColor.value || currentTurn.value !== myColor.value || board.value[i][j] !== 0) return;
  
  if (previewX.value === i && previewY.value === j) {
    try {
      const data = await commonFetch(`${APP_CONFIG.API_BASE}/gomoku/gomoku_move.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ room_code: currentRoomCode.value, user_id: userId.value, x: i, y: j })
      });
      if (!data.success) {
        gameMsg.value = data.message || '落子失败';
      } else {
        gameMsg.value = '';
        previewX.value = null;
        previewY.value = null;
        fetchAndRenderRoom();
      }
    } catch(e) {
      gameMsg.value = '网络错误';
    }
  } else {
    previewX.value = i;
    previewY.value = j;
  }
};

const initiateRematch = async (room) => {
  let to_user_id = myColor.value === 1 ? room.player2_id : room.player1_id;
  if (!to_user_id) return;
  rematchWaiting = true;
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/gomoku/gomoku_rematch_invite.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ from_user_id: userId.value, to_user_id, old_room_code: currentRoomCode.value, from_username: username.value })
    });
    if (data.success) {
      customAlert('已创建新房间，等待对方加入...');
      enterRoom(data.new_room_code);
    } else {
      customAlert('邀请失败');
      rematchWaiting = false;
    }
  } catch(e) {
    customAlert('网络错误');
    rematchWaiting = false;
  }
};

const pollRematchInvite = () => {
  rematchPollingTimer = setInterval(async () => {
    if (rematchWaiting) return;
    try {
      const data = await commonFetch(`${APP_CONFIG.API_BASE}/gomoku/gomoku_check_rematch.php?user_id=${userId.value}`);
      if (data.invite && data.invite.status === 'pending') {
        const inviteId = data.invite.id;
        const newCode = data.invite.new_room_code;
        if (await customConfirm('对方邀请你再来一局，是否同意？')) {
          await commonFetch(`${APP_CONFIG.API_BASE}/gomoku/gomoku_respond_rematch.php`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ invite_id: inviteId, action: 'accept' })
          });
          clearInterval(rematchPollingTimer);
          enterRoom(newCode);
        } else {
          await commonFetch(`${APP_CONFIG.API_BASE}/gomoku/gomoku_respond_rematch.php`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ invite_id: inviteId, action: 'reject' })
          });
        }
      }
    } catch(e) {}
  }, 1500);
};
</script>

<style scoped>
.gomoku-page-wrapper {
  min-height: 100vh;
  background: var(--bg-color-light, #f6f8fa);
}

.gomoku-header {
  background: var(--header-bg, #ffffff);
  display: flex; align-items: center; padding: 14px 16px;
  box-shadow: 0 2px 12px var(--shadow-color, rgba(0,0,0,0.08));
  position: fixed; top: 0; left: 0; right: 0; z-index: 100;
}

.gomoku-back { background: transparent; border: none; cursor: pointer; display: flex; align-items: center; }
.gomoku-title { flex: 1; text-align: center; font-size: 20px; font-weight: bold; color: var(--text-color-light, #333); }

.gomoku-main { padding: 80px 16px 40px 16px; max-width: 600px; margin: 0 auto; }

.gomoku-room-list { background: var(--bg-color-card, #fff); border-radius: 12px; padding: 20px; box-shadow: 0 4px 12px var(--shadow-color, rgba(0,0,0,0.05)); }

.gomoku-room-list-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; font-weight: bold; color: var(--text-color-light, #333); }

.gomoku-join-btn { background: #4f8cff; color: white; border: none; padding: 8px 16px; border-radius: 20px; cursor: pointer; }
.gomoku-join-btn:disabled { background: var(--border-color, #ccc); cursor: not-allowed; }

.gomoku-room-item { display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid var(--border-color, #eee); color: var(--text-color-light, #333); }

.gomoku-game-area { background: var(--bg-color-card, #fff); border-radius: 12px; padding: 20px; box-shadow: 0 4px 12px var(--shadow-color, rgba(0,0,0,0.05)); }

.gomoku-exit-btn { background: transparent; color: #4f8cff; border: none; cursor: pointer; margin-bottom: 16px; }

.gomoku-players { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; font-size: 16px; color: var(--text-color-light, #333); }
.gomoku-player { padding: 6px 12px; border-radius: 20px; border: 2px solid transparent; transition: all 0.3s; }
.gomoku-player.active { border-color: #4f8cff; font-weight: bold; background: var(--bg-color-light, #eef4ff); }

.gomoku-turn-info { text-align: center; color: var(--text-color-medium, #666); margin-bottom: 16px; }

.gomoku-board-wrapper { display: flex; justify-content: center; }
.gomoku-board { 
  display: grid; grid-template-columns: repeat(15, 20px); grid-template-rows: repeat(15, 20px);
  background: #dbb37a; padding: 10px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}
@media (min-width: 400px) {
  .gomoku-board { grid-template-columns: repeat(15, 22px); grid-template-rows: repeat(15, 22px); }
}

.gomoku-cell { width: 100%; height: 100%; border: 1px solid rgba(0,0,0,0.3); box-sizing: border-box; position: relative; cursor: pointer; }
.gomoku-stone { width: 80%; height: 80%; border-radius: 50%; position: absolute; top: 10%; left: 10%; box-shadow: 1px 1px 3px rgba(0,0,0,0.5); }
.gomoku-stone.black { background: radial-gradient(circle at 30% 30%, #4f8cff, #005bb5); }
.gomoku-stone.white { background: radial-gradient(circle at 30% 30%, #fff, #ccc); }
.gomoku-stone.shadow { opacity: 0.5; }

.gomoku-game-msg { text-align: center; margin-top: 16px; color: #ff4d4d; min-height: 20px; }
</style>
