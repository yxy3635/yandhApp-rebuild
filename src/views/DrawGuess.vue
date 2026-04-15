<template>
  <div class="draw-container">
    <div id="drawRoomList" v-show="!inRoom">
        <header class="gomoku-header">
            <button id="drawBackBtn" class="gomoku-back" @click="goBack">←</button>
            <span class="gomoku-title">你画我猜</span>
        </header>
        <div class="gomoku-room-list">
            <div class="gomoku-room-list-header">
                <span>房间列表</span>
                <div style="display:flex;gap:10px;">
                    <button class="gomoku-join-btn" style="padding:8px 16px;" @click="createRoom">＋ 创建房间</button>
                    <button class="gomoku-join-btn" style="padding:8px 16px;background:linear-gradient(90deg,#7aa2f7,#4f8cff);" @click="loadRooms">刷新</button>
                </div>
            </div>
            <div id="drawRoomsList">
                <div v-if="loadingRooms" class="gomoku-empty loading">加载中...</div>
                <div v-else-if="rooms.length === 0" class="gomoku-empty">暂无房间，快来创建吧！</div>
                <div v-else v-for="room in rooms" :key="room.id" class="gomoku-room-item">
                    <span class="gomoku-room-name">{{ room.room_name }}</span>
                    <button class="gomoku-join-btn" @click="joinRoom(room.room_code)">
                        {{ room.player_count >= 2 ? '进入' : '加入' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="drawGameArea" v-show="inRoom" style="display: flex; flex-direction: column; height: 100vh;">
        <div class="draw-header">
            <button id="drawExitBtn" class="draw-back-btn" @click="exitRoom">←</button>
            <h2 id="drawRoomTitle">序号: {{ currentRoomCode }}</h2>
            <div class="draw-status" id="drawGameStatus">{{ gameStatusText }}</div>
        </div>

        <div class="draw-game-info">
            <div class="draw-players">
                <div class="draw-player" :class="{active: isP1Drawing}">
                    <span class="draw-player-name">{{ p1Name }}</span>
                    <span class="draw-player-score">{{ p1Score }}分</span>
                </div>
                <div class="vs">VS</div>
                <div class="draw-player" :class="{active: isP2Drawing}">
                    <span class="draw-player-name">{{ p2Name }}</span>
                    <span class="draw-player-score">{{ p2Score }}分</span>
                </div>
            </div>
            
            <div class="draw-game-controls">
                <div class="draw-word-hint">{{ wordHint }}</div>
                <div class="draw-guess-attempts" v-show="showGuessAttempts">猜测机会: {{ guessAttempts }}/5</div>
                <div class="draw-control-buttons">
                    <button class="draw-btn draw-btn-primary" v-show="showStartBtn" @click="startGame">{{ startBtnText }}</button>
                    <button class="draw-btn draw-btn-warning" v-show="showSkipBtn" @click="skipWord">跳过此题</button>
                    <button class="draw-btn draw-btn-danger" v-show="showEndBtn" @click="endGame">结束比赛</button>
                </div>
            </div>
        </div>

        <div class="draw-main-area">
            <div class="draw-canvas-area" ref="canvasContainerRef">
                <div class="draw-tools">
                    <button class="draw-tool" :class="{active: currentTool==='pen'}" @click="selectTool('pen')" title="画笔">🖊️</button>
                    <button class="draw-tool" :class="{active: currentTool==='eraser'}" @click="selectTool('eraser')" title="橡皮">🧹</button>
                    <input type="color" v-model="brushColor" title="颜色">
                    <input type="range" v-model.number="brushSize" min="1" max="20" title="画笔大小">
                    <button class="draw-tool" :class="{active: currentTool==='hand'}" @click="selectTool('hand')" title="拖拽视图">✋</button>
                    <button class="draw-tool" @click="clearCanvas" title="清空画板">🗑️</button>
                    <button class="draw-tool" @click="showHelp" title="使用说明">❔</button>
                </div>
                <canvas ref="canvasRef" width="800" height="600" 
                        @mousedown="onMouseDown" @mousemove="onMouseMove" @mouseup="onMouseUp" @mouseout="onMouseUp"
                        @touchstart="onTouchStart" @touchmove="onTouchMove" @touchend="onTouchEnd"
                ></canvas>
                <div class="draw-canvas-disabled" v-show="showCanvasDisabled">
                    <p>到你猜啦~</p>
                    <p>答案在下面输入噢！</p>
                </div>
            </div>

            <div class="draw-chat-area">
                <div class="draw-chat-header">💬 聊天&猜词</div>
                <div class="draw-chat-messages" ref="chatMessagesRef">
                    <div v-for="(msg, idx) in messages" :key="idx">
                        <div v-if="msg.is_system" class="draw-system-message">{{ msg.message }}</div>
                        <div v-else-if="msg.is_correct" class="draw-message draw-correct-message">
                            <span class="draw-message-sender">{{ msg.nickname }}:</span>{{ msg.message }} ✓
                        </div>
                        <div v-else class="draw-message draw-wrong-message">
                            <span class="draw-message-sender">{{ msg.nickname }}:</span>{{ msg.message }}
                        </div>
                    </div>
                </div>
                <div class="draw-chat-input">
                    <input type="text" v-model="chatInput" placeholder="输入你的答案..." maxlength="100" @keypress.enter="sendMessage">
                    <button @click="sendMessage">发送</button>
                </div>
            </div>
        </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, computed, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import { APP_CONFIG, commonFetch } from '../utils/config';
import { customAlert, customConfirm, customPrompt } from '../utils/modal';

const router = useRouter();
const userId = ref(localStorage.getItem('user_id'));
const username = ref(localStorage.getItem('username') || '');

const inRoom = ref(false);
const rooms = ref([]);
const loadingRooms = ref(false);
const currentRoomCode = ref('');

// Game state
const gameStatus = ref('waiting');
const p1Name = ref('等待玩家');
const p2Name = ref('等待玩家');
const p1Score = ref(0);
const p2Score = ref(0);
const isP1Drawing = ref(false);
const isP2Drawing = ref(false);
const currentDrawer = ref(null);
const currentWord = ref('');
const currentRound = ref(1);
const guessAttempts = ref(5);
const playerCount = ref(0);

// Chat state
const messages = ref([{ is_system: true, message: '欢迎来到你画我猜游戏！' }]);
const chatInput = ref('');
const chatMessagesRef = ref(null);

// Canvas & Tools state
const canvasRef = ref(null);
const canvasContainerRef = ref(null);
let ctx = null;
let offCanvas = null;
let offCtx = null;
let viewScale = 1;
let viewTranslateX = 0;
let viewTranslateY = 0;
const currentTool = ref('pen');
const brushColor = ref('#000000');
const brushSize = ref(3);
let isDrawing = false;
let isPanning = false;
let isPinching = false;
let lastPoint = null;
let panStartScreen = {x:0, y:0};
let panStartTranslate = {x:0, y:0};
let pinchStartScale = 1;
let pinchStartDist = 0;
let lastCanvasData = '';

let roomStatusTimer = null;
let hasShownFinal = false;

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
  window.addEventListener('resize', handleResize);
});

onBeforeUnmount(() => {
  if (roomStatusTimer) clearInterval(roomStatusTimer);
  window.removeEventListener('resize', handleResize);
});

const goBack = () => {
    router.back();
};

const loadRooms = async () => {
  loadingRooms.value = true;
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/draw/list_rooms.php`);
    if (data.success && data.rooms) {
      rooms.value = data.rooms;
    } else {
      rooms.value = [];
    }
  } catch(e) {
  } finally {
      loadingRooms.value = false;
  }
};

const createRoom = async () => {
  if (!await customConfirm('确定要创建房间吗？')) return;
  const roomName = `${username.value}的房间`;
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/draw/create_room.php`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ user_id: userId.value, room_name: roomName, nickname: username.value })
    });
    if (data.success) {
      joinRoom(data.room_code);
    } else {
      customAlert('创建失败');
    }
  } catch(e) { customAlert('网络错误'); }
};

const joinRoom = async (code) => {
  try {
    const data = await commonFetch(`${APP_CONFIG.API_BASE}/draw/join_room.php`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ user_id: userId.value, room_code: code, nickname: username.value })
    });
    if (data.success) {
      currentRoomCode.value = code;
      inRoom.value = true;
      hasShownFinal = false;
      messages.value = [{ is_system: true, message: '欢迎来到你画我猜游戏！' }];
      nextTick(() => {
        initCanvas();
        startPolling();
      });
    } else {
      customAlert(data.message || '加入失败');
    }
  } catch(e) { customAlert('网络错误'); }
};

const exitRoom = () => {
    inRoom.value = false;
    currentRoomCode.value = '';
    if (roomStatusTimer) clearInterval(roomStatusTimer);
    loadRooms();
};

const startPolling = () => {
    if (roomStatusTimer) clearInterval(roomStatusTimer);
    poll();
    roomStatusTimer = setInterval(poll, 2000);
};

const poll = async () => {
    if (!currentRoomCode.value) return;
    try {
        const data = await commonFetch(`${APP_CONFIG.API_BASE}/draw/room_status.php?room_code=${currentRoomCode.value}&user_id=${userId.value}`);
        if (data.success) {
            const room = data.room;
            if (data.game_ended_by) {
                clearInterval(roomStatusTimer);
                customAlert(`${data.game_ended_by} 主动结束了比赛`);
                exitRoom();
                return;
            }
            
            gameStatus.value = room.game_status;
            p1Name.value = getPlayerName(room.player1_id, room.player1_name, '等待玩家');
            p2Name.value = getPlayerName(room.player2_id, room.player2_name, '等待玩家');
            p1Score.value = room.player1_score || 0;
            p2Score.value = room.player2_score || 0;
            currentDrawer.value = room.current_drawer;
            currentWord.value = room.current_word;
            currentRound.value = room.current_round;
            guessAttempts.value = room.guess_attempts || 5;
            playerCount.value = room.player_count;
            
            isP1Drawing.value = String(room.current_drawer) === String(room.player1_id);
            isP2Drawing.value = String(room.current_drawer) === String(room.player2_id);
            
            if (room.game_status === 'finished' && !data.game_ended_by && !hasShownFinal) {
                hasShownFinal = true;
                customAlert(`比赛结束\n${p1Name.value}：${p1Score.value}分\n${p2Name.value}：${p2Score.value}分`);
                exitRoom();
                return;
            }
            
            if (data.canvas_changed) {
                loadCanvasFromServer();
            }
            loadChatMessages();
            
        } else if (data.message && data.message.includes('房间不存在')) {
            customAlert('游戏已结束，房间已关闭');
            exitRoom();
        }
    } catch(e) {}
};

const gameStatusText = computed(() => {
    const map = { 'waiting': '等待开始', 'playing': '游戏中', 'finished': '已结束' };
    return map[gameStatus.value] || '未知';
});

const isCurrentDrawer = computed(() => String(currentDrawer.value) === String(userId.value));
const isPlaying = computed(() => gameStatus.value === 'playing');
const isWaitingForWord = computed(() => gameStatus.value === 'waiting' && currentWord.value === 'WAITING_FOR_WORD');

const wordHint = computed(() => {
    if (gameStatus.value === 'waiting' && currentWord.value !== 'WAITING_FOR_WORD') return '等待游戏开始';
    if (gameStatus.value === 'finished') return '游戏结束';
    if (isWaitingForWord.value) {
        return isCurrentDrawer.value ? `第${currentRound.value}局 - 请选择词汇` : `第${currentRound.value}局 - 等待画家选择词汇...`;
    }
    if (isCurrentDrawer.value) {
        return `第${currentRound.value}局 - 你来画: ${currentWord.value || '...'}`;
    } else {
        const hint = currentWord.value ? currentWord.value.replace(/./g, '_ ').trim() : '';
        return `第${currentRound.value}局 - 请猜: ${hint}`;
    }
});

const showGuessAttempts = computed(() => isPlaying.value && !isCurrentDrawer.value && currentWord.value !== 'WAITING_FOR_WORD');
const showStartBtn = computed(() => {
    const canStart = gameStatus.value === 'waiting' && playerCount.value >= 2 && currentWord.value !== 'WAITING_FOR_WORD';
    // Assume owner is player1 for simplicity or just allow anyone to start if canStart
    return canStart || (isWaitingForWord.value && isCurrentDrawer.value);
});
const startBtnText = computed(() => (isWaitingForWord.value && isCurrentDrawer.value) ? '选择词汇开始' : '开始游戏');
const showSkipBtn = computed(() => isCurrentDrawer.value && isPlaying.value);
const showEndBtn = computed(() => isPlaying.value);
const showCanvasDisabled = computed(() => !isCurrentDrawer.value && (isPlaying.value || isWaitingForWord.value));

const startGame = async () => {
    let customWord = '';
    if (isWaitingForWord.value && isCurrentDrawer.value || (gameStatus.value === 'waiting' && playerCount.value >= 2)) {
        const res = await customPrompt('请输入要画的词语（留空则随机选择）：');
        if (res === null) return;
        customWord = res.trim();
    }
    
    try {
        const req = { user_id: userId.value, room_code: currentRoomCode.value };
        if (customWord) req.custom_word = customWord;
        
        const data = await commonFetch(`${APP_CONFIG.API_BASE}/draw/start_game.php`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(req)
        });
        
        if (data.success) {
            clearCanvasLocal();
        } else {
            customAlert('开始失败: ' + data.message);
        }
    } catch(e) {}
};

const skipWord = async () => {
    try {
        const data = await commonFetch(`${APP_CONFIG.API_BASE}/draw/skip_word.php`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId.value, room_code: currentRoomCode.value })
        });
        if (data.success) clearCanvasLocal();
    } catch(e) {}
};

const endGame = async () => {
    if (!await customConfirm('确定结束比赛吗？')) return;
    try {
        await commonFetch(`${APP_CONFIG.API_BASE}/draw/end_game.php`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId.value, room_code: currentRoomCode.value })
        });
    } catch(e) {}
};

// Chat
const sendMessage = async () => {
    const msg = chatInput.value.trim();
    if (!msg || !currentRoomCode.value) return;
    try {
        const data = await commonFetch(`${APP_CONFIG.API_BASE}/draw/send_message.php`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId.value, room_code: currentRoomCode.value, message: msg, nickname: username.value })
        });
        if (data.success) {
            chatInput.value = '';
            loadChatMessages();
        }
    } catch(e) {}
};

const loadChatMessages = async () => {
    try {
        const data = await commonFetch(`${APP_CONFIG.API_BASE}/draw/get_messages.php?room_code=${currentRoomCode.value}`);
        if (data.success) {
            messages.value = data.messages;
            nextTick(() => {
                if (chatMessagesRef.value) chatMessagesRef.value.scrollTop = chatMessagesRef.value.scrollHeight;
            });
        }
    } catch(e) {}
};

// Canvas
const handleResize = () => {
    // Basic resize handling
};

const initCanvas = () => {
    if (!canvasRef.value) return;
    canvasRef.value.width = 800;
    canvasRef.value.height = 600;
    ctx = canvasRef.value.getContext('2d');
    ctx.lineCap = 'round'; ctx.lineJoin = 'round';
    
    offCanvas = document.createElement('canvas');
    offCanvas.width = 800; offCanvas.height = 600;
    offCtx = offCanvas.getContext('2d');
    offCtx.lineCap = 'round'; offCtx.lineJoin = 'round';
    
    clearCanvasLocal();
};

const selectTool = (t) => { currentTool.value = t; };

const clearCanvasLocal = () => {
    if (offCtx) {
        offCtx.fillStyle = '#fff';
        offCtx.fillRect(0, 0, 800, 600);
    }
    renderCanvas();
    saveCanvasToServer();
};

const clearCanvas = () => {
    if (!isCurrentDrawer.value) return;
    clearCanvasLocal();
};

const renderCanvas = () => {
    if (!ctx || !offCanvas) return;
    ctx.save();
    ctx.setTransform(1,0,0,1,0,0);
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, 800, 600);
    ctx.setTransform(viewScale, 0, 0, viewScale, viewTranslateX, viewTranslateY);
    ctx.drawImage(offCanvas, 0, 0);
    ctx.restore();
};

const getCoords = (e) => {
    const rect = canvasRef.value.getBoundingClientRect();
    const scaleX = 800 / rect.width;
    const scaleY = 600 / rect.height;
    const cx = e.touches ? e.touches[0].clientX : e.clientX;
    const cy = e.touches ? e.touches[0].clientY : e.clientY;
    return { x: (cx - rect.left) * scaleX, y: (cy - rect.top) * scaleY };
};

const screenToWorld = (x, y) => ({ x: (x - viewTranslateX)/viewScale, y: (y - viewTranslateY)/viewScale });

const onMouseDown = (e) => startDraw(e);
const onMouseMove = (e) => draw(e);
const onMouseUp = () => stopDraw();
const onTouchStart = (e) => {
    if (e.touches.length === 2) {
        isPinching = true;
        // Simple pinch support omited for brevity, hand tool works
    } else {
        startDraw(e);
    }
};
const onTouchMove = (e) => { if(e.touches.length===1) draw(e); };
const onTouchEnd = () => stopDraw();

const startDraw = (e) => {
    if (!ctx) return;
    if (currentTool.value === 'hand') {
        isPanning = true;
        const coords = getCoords(e);
        panStartScreen = {x: coords.x, y: coords.y};
        panStartTranslate = {x: viewTranslateX, y: viewTranslateY};
        return;
    }
    if (!isCurrentDrawer.value) return;
    isDrawing = true;
    const c = getCoords(e);
    const w = screenToWorld(c.x, c.y);
    offCtx.beginPath();
    offCtx.moveTo(w.x, w.y);
    lastPoint = w;
};

const draw = (e) => {
    if (!ctx) return;
    if (currentTool.value === 'hand' && isPanning) {
        const c = getCoords(e);
        viewTranslateX = panStartTranslate.x + (c.x - panStartScreen.x);
        viewTranslateY = panStartTranslate.y + (c.y - panStartScreen.y);
        renderCanvas();
        return;
    }
    if (!isDrawing || !isCurrentDrawer.value) return;
    const c = getCoords(e);
    const w = screenToWorld(c.x, c.y);
    
    if (currentTool.value === 'pen') {
        offCtx.globalCompositeOperation = 'source-over';
        offCtx.strokeStyle = brushColor.value;
        offCtx.lineWidth = brushSize.value / viewScale;
    } else if (currentTool.value === 'eraser') {
        offCtx.globalCompositeOperation = 'destination-out';
        offCtx.lineWidth = (brushSize.value * 2) / viewScale;
    }
    offCtx.lineTo(w.x, w.y);
    offCtx.stroke();
    renderCanvas();
};

const stopDraw = () => {
    isPanning = false;
    if (isDrawing && isCurrentDrawer.value) {
        isDrawing = false;
        saveCanvasToServer();
    }
};

const saveCanvasToServer = async () => {
    if (!currentRoomCode.value || !offCanvas) return;
    const dataUrl = offCanvas.toDataURL();
    if (dataUrl === lastCanvasData) return;
    try {
        await commonFetch(`${APP_CONFIG.API_BASE}/draw/save_canvas.php`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId.value, room_code: currentRoomCode.value, canvas_data: dataUrl })
        });
        lastCanvasData = dataUrl;
    } catch(e) {}
};

const loadCanvasFromServer = async () => {
    if (!currentRoomCode.value || !ctx) return;
    try {
        const data = await commonFetch(`${APP_CONFIG.API_BASE}/draw/get_canvas.php?room_code=${currentRoomCode.value}`);
        if (data.success && data.canvas_data && data.canvas_data !== lastCanvasData) {
            const img = new Image();
            img.onload = () => {
                offCtx.clearRect(0, 0, 800, 600);
                offCtx.globalCompositeOperation = 'source-over';
                offCtx.drawImage(img, 0, 0);
                renderCanvas();
            };
            img.src = data.canvas_data;
            lastCanvasData = data.canvas_data;
        }
    } catch(e) {}
};

const showHelp = () => {
    customAlert("工具：画笔、橡皮、颜色、大小、手型平移\n只有画家能画，猜者只能在聊天框回答。");
};
</script>

<style scoped>
.draw-container {
    height: 100vh;
    display: flex;
    flex-direction: column;
    background: var(--bg-color-light, #f6f8fa);
}

.gomoku-header {
  background: var(--header-bg, #ffffff);
  display: flex; align-items: center; padding: 14px 16px;
  box-shadow: 0 2px 12px var(--shadow-color, rgba(0,0,0,0.08));
}

.gomoku-back { background: transparent; border: none; font-size: 20px; font-weight: bold; cursor: pointer; color: var(--nav-btn-active-color, #4f8cff); }
.gomoku-title { flex: 1; text-align: center; font-size: 20px; font-weight: bold; color: var(--text-color-light, #333); }

.gomoku-room-list { background: var(--bg-color-card, #fff); border-radius: 12px; padding: 20px; margin: 20px; box-shadow: 0 4px 12px var(--shadow-color, rgba(0,0,0,0.05)); }

.gomoku-room-list-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; font-weight: bold; color: var(--text-color-light, #333); }

.gomoku-join-btn { background: var(--nav-btn-active-color, #4f8cff); color: white; border: none; padding: 8px 16px; border-radius: 20px; cursor: pointer; }
.gomoku-join-btn:disabled { background: var(--border-color, #ccc); cursor: not-allowed; }

.gomoku-room-item { display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid var(--border-color, #eee); color: var(--text-color-light, #333); }

.draw-header { display: flex; align-items: center; padding: 10px; background: var(--bg-color-card, #fff); box-shadow: 0 2px 8px var(--shadow-color, rgba(0,0,0,0.05)); color: var(--text-color-light, #333); }
.draw-back-btn { font-size: 20px; background: none; border: none; color: var(--nav-btn-active-color, #4f8cff); cursor: pointer; margin-right: 10px; }
#drawRoomTitle { flex: 1; font-size: 16px; margin: 0; }
.draw-status { font-size: 14px; color: var(--text-color-medium, #666); }

.draw-game-info { padding: 10px; background: var(--bg-color-card, #fff); margin-bottom: 10px; box-shadow: 0 2px 8px var(--shadow-color, rgba(0,0,0,0.05)); color: var(--text-color-light, #333); }

.draw-players { display: flex; justify-content: space-around; align-items: center; margin-bottom: 10px; }
.draw-player { display: flex; flex-direction: column; align-items: center; padding: 5px 10px; border-radius: 8px; border: 2px solid transparent; }
.draw-player.active { border-color: var(--nav-btn-active-color, #4f8cff); background: var(--bg-color-light, #eef4ff); }
.draw-player-name { font-weight: bold; }
.draw-player-score { color: var(--nav-btn-active-color, #4f8cff); font-weight: bold; }

.draw-game-controls { text-align: center; }
.draw-word-hint { font-size: 18px; font-weight: bold; margin-bottom: 10px; }
.draw-guess-attempts { color: var(--text-color-danger, #ff6b6b); font-size: 14px; margin-bottom: 10px; }
.draw-control-buttons { display: flex; justify-content: center; gap: 10px; }
.draw-btn { padding: 8px 16px; border-radius: 8px; border: none; font-weight: bold; cursor: pointer; color: white; }
.draw-btn-primary { background: var(--nav-btn-active-color, #4f8cff); }
.draw-btn-warning { background: #ffb142; }
.draw-btn-danger { background: var(--text-color-danger, #ff4d4d); }

.draw-main-area { flex: 1; display: flex; flex-direction: column; gap: 10px; padding: 10px; overflow: hidden; }
@media (min-width: 768px) { .draw-main-area { flex-direction: row; } }

.draw-canvas-area { flex: 2; display: flex; flex-direction: column; background: var(--bg-color-card, #fff); border-radius: 12px; overflow: hidden; position: relative; border: 1px solid var(--border-color, transparent); }
.draw-tools { display: flex; padding: 10px; gap: 10px; background: var(--bg-color-light, #f0f4f8); overflow-x: auto; }
.draw-tool { background: var(--bg-color-card, white); border: 1px solid var(--border-color, #ddd); padding: 5px 10px; border-radius: 6px; cursor: pointer; font-size: 16px; color: var(--text-color-light, #333); }
.draw-tool.active { background: var(--nav-btn-active-color, #4f8cff); color: white; border-color: var(--nav-btn-active-color, #4f8cff); }
canvas { flex: 1; width: 100%; height: 100%; cursor: crosshair; touch-action: none; background: white; }
.draw-canvas-disabled { position: absolute; top: 50px; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; flex-direction: column; justify-content: center; align-items: center; font-size: 24px; font-weight: bold; color: #fff; z-index: 10; pointer-events: none; }

.draw-chat-area { flex: 1; display: flex; flex-direction: column; background: var(--bg-color-card, #fff); border-radius: 12px; overflow: hidden; border: 1px solid var(--border-color, transparent); }
.draw-chat-header { padding: 10px; background: var(--bg-color-light, #f0f4f8); font-weight: bold; text-align: center; color: var(--text-color-light, #333); }
.draw-chat-messages { flex: 1; padding: 10px; overflow-y: auto; display: flex; flex-direction: column; gap: 5px; }
.draw-message { padding: 5px 10px; border-radius: 8px; font-size: 14px; }
.draw-system-message { text-align: center; color: var(--text-color-medium, #999); font-size: 12px; margin: 5px 0; }
.draw-wrong-message { background: var(--bg-color-light, #f5f5f5); color: var(--text-color-light, #333); }
.draw-correct-message { background: #1b4524; color: #81c784; font-weight: bold; }
.draw-message-sender { font-weight: bold; margin-right: 5px; }
.draw-chat-input { display: flex; padding: 10px; border-top: 1px solid var(--border-color, #eee); }
.draw-chat-input input { flex: 1; padding: 8px; border: 1px solid var(--border-color, #ddd); border-radius: 6px 0 0 6px; outline: none; background: var(--bg-color-light, #fff); color: var(--text-color-light, #333); }
.draw-chat-input button { padding: 8px 15px; background: var(--nav-btn-active-color, #4f8cff); color: white; border: none; border-radius: 0 6px 6px 0; cursor: pointer; }
</style>
