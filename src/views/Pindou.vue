<template>
  <div class="pindou-container">
    <AppHeader title="拼豆图纸" :showBack="true" />

    <div class="container" style="margin-top: 70px;">
        <main>
            <section class="controls">
                <div class="control-group file-upload">
                    <label for="imageUpload" class="custom-file-upload">
                        {{ uploadLabelText }}
                    </label>
                    <input type="file" id="imageUpload" accept="image/*" @change="onImageUpload">
                </div>

                <div class="control-group">
                    <label for="gridWidth">宽度 (珠子数量):</label>
                    <div class="range-input-wrapper">
                        <input type="range" id="gridWidth" min="10" max="150" v-model.number="gridWidth" @input="syncWidth">
                        <input type="number" id="gridWidthInput" min="10" max="150" v-model.number="gridWidth" @change="syncWidth">
                    </div>
                </div>

                <div class="control-group">
                    <label for="paletteSelect">色卡选择:</label>
                    <select id="paletteSelect" v-model="palette">
                        <option value="mard72">MARD 72色</option>
                        <option value="basic">基础 16 色</option>
                        <option value="grayscale">黑白灰</option>
                    </select>
                </div>
                
                <div class="control-group checkbox-group">
                    <input type="checkbox" id="showGrid" v-model="showGrid">
                    <label for="showGrid">显示网格</label>
                </div>
                
                <button id="processBtn" class="primary-btn" @click="processImage">{{ processBtnText }}</button>
                <button id="downloadBtn" class="secondary-btn" :disabled="!quantizedResult" @click="downloadFullSheet">保存图片</button>
            </section>

            <section class="preview-area">
                <div class="canvas-wrapper">
                    <div id="emptyState" class="empty-state" v-show="!originalImage">
                        <div class="empty-icon">🧩</div>
                        <p>请先上传图片</p>
                    </div>
                    <canvas ref="mainCanvas" v-show="originalImage" @mousemove="onCanvasMouseMove" @mouseleave="onCanvasMouseLeave"></canvas>
                    <div ref="tooltipRef" class="tooltip" :class="{ visible: tooltipVisible }" :style="tooltipStyle" v-html="tooltipHtml"></div>
                </div>

                <div id="stats" class="stats-panel" v-show="quantizedResult">
                    <div class="stats-header">
                        <h3>用量清单</h3>
                    </div>
                    <ul id="colorList">
                        <li v-for="item in statsData" :key="item.code">
                            <span class="color-swatch" :style="{ backgroundColor: item.hex }"></span>
                            <span class="color-info">
                                <span class="color-code">[{{ item.code }}]</span>
                                <span class="color-name">{{ item.name }}</span>
                            </span>
                            <span class="color-count">x <strong>{{ item.count }}</strong></span>
                        </li>
                    </ul>
                </div>
            </section>
        </main>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import { customAlert } from '../utils/modal';

const router = useRouter();

const goBack = () => {
    router.back();
};

const uploadLabelText = ref('选择图片');
const gridWidth = ref(50);
const palette = ref('mard72');
const showGrid = ref(true);
const processBtnText = ref('生成图纸');

const originalImage = ref(null);
const quantizedResult = ref(null);

const mainCanvas = ref(null);
const tooltipRef = ref(null);
const tooltipVisible = ref(false);
const tooltipStyle = ref({});
const tooltipHtml = ref('');

const statsData = ref([]);

const PALETTE_BASIC = [
    { code: "W", name: "白色", hex: "#FFFFFF" },
    { code: "BK", name: "黑色", hex: "#000000" },
    { code: "G1", name: "灰色", hex: "#8B8B8B" },
    { code: "R1", name: "红色", hex: "#C60C30" },
    { code: "O1", name: "橙色", hex: "#FF8200" },
    { code: "Y1", name: "黄色", hex: "#FFC627" },
    { code: "GR1", name: "绿色", hex: "#009639" },
    { code: "GR2", name: "深绿", hex: "#004B23" },
    { code: "B1", name: "天蓝", hex: "#00AEEF" },
    { code: "B2", name: "深蓝", hex: "#0033A0" },
    { code: "P1", name: "紫色", hex: "#702082" },
    { code: "PK1", name: "粉色", hex: "#F49AC1" },
    { code: "BR1", name: "棕色", hex: "#4E3629" },
    { code: "SK1", name: "肉色", hex: "#F2C9A9" },
    { code: "T1", name: "透明", hex: "#EAEAEA" },
];

const PALETTE_MARD_72 = [
    { code: "A1", name: "奶油黄", hex: "#FFFFCC" },
    { code: "A2", name: "浅黄", hex: "#FFFF99" },
    { code: "A3", name: "柠檬黄", hex: "#FFEB3B" },
    { code: "A4", name: "中黄", hex: "#FFD700" },
    { code: "A5", name: "蛋黄", hex: "#FFC107" },
    { code: "A6", name: "橘黄", hex: "#FFB300" },
    { code: "A7", name: "深橘黄", hex: "#FFA000" },
    { code: "A8", name: "浅橙", hex: "#FFCC80" },
    { code: "A9", name: "橙色", hex: "#FF9800" },
    { code: "A10", name: "深橙", hex: "#F57C00" },
    { code: "A11", name: "红橙", hex: "#EF6C00" },
    { code: "A12", name: "浅杏", hex: "#FFE0B2" },
    { code: "A13", name: "深杏", hex: "#FFCCBC" },
    { code: "B1", name: "荧光绿", hex: "#CCFF90" },
    { code: "B2", name: "嫩绿", hex: "#B2FF59" },
    { code: "B3", name: "浅绿", hex: "#76FF03" },
    { code: "B4", name: "草绿", hex: "#64DD17" },
    { code: "B5", name: "中绿", hex: "#43A047" },
    { code: "B6", name: "深绿", hex: "#2E7D32" },
    { code: "B7", name: "橄榄绿", hex: "#558B2F" },
    { code: "B8", name: "墨绿", hex: "#33691E" },
    { code: "B9", name: "青绿", hex: "#00E676" },
    { code: "B10", name: "薄荷绿", hex: "#69F0AE" },
    { code: "B11", name: "松石绿", hex: "#1DE9B6" },
    { code: "B12", name: "孔雀绿", hex: "#00BFA5" },
    { code: "C1", name: "淡蓝", hex: "#E3F2FD" },
    { code: "C2", name: "浅天蓝", hex: "#BBDEFB" },
    { code: "C3", name: "天蓝", hex: "#90CAF9" },
    { code: "C4", name: "湖蓝", hex: "#64B5F6" },
    { code: "C5", name: "中蓝", hex: "#42A5F5" },
    { code: "C6", name: "宝蓝", hex: "#2196F3" },
    { code: "C7", name: "深蓝", hex: "#1976D2" },
    { code: "C8", name: "藏青", hex: "#0D47A1" },
    { code: "C9", name: "浅青", hex: "#B3E5FC" },
    { code: "C10", name: "青色", hex: "#03A9F4" },
    { code: "C11", name: "深青", hex: "#0288D1" },
    { code: "C12", name: "靛蓝", hex: "#303F9F" },
    { code: "D1", name: "浅紫", hex: "#F3E5F5" },
    { code: "D2", name: "香芋紫", hex: "#E1BEE7" },
    { code: "D3", name: "中紫", hex: "#CE93D8" },
    { code: "D4", name: "深紫", hex: "#BA68C8" },
    { code: "D5", name: "葡萄紫", hex: "#AB47BC" },
    { code: "D6", name: "暗紫", hex: "#8E24AA" },
    { code: "D7", name: "深暗紫", hex: "#6A1B9A" },
    { code: "D8", name: "蓝紫", hex: "#7E57C2" },
    { code: "D9", name: "深蓝紫", hex: "#512DA8" },
    { code: "E1", name: "淡粉", hex: "#FFEBEE" },
    { code: "E2", name: "浅粉", hex: "#FFCDD2" },
    { code: "E3", name: "粉红", hex: "#EF9A9A" },
    { code: "E4", name: "桃红", hex: "#E57373" },
    { code: "E5", name: "玫红", hex: "#F48FB1" },
    { code: "E6", name: "深玫红", hex: "#F06292" },
    { code: "E7", name: "紫红", hex: "#EC407A" },
    { code: "E8", name: "深紫红", hex: "#D81B60" },
    { code: "F1", name: "浅红", hex: "#FF8A80" },
    { code: "F2", name: "朱红", hex: "#FF5252" },
    { code: "F3", name: "大红", hex: "#FF1744" },
    { code: "F4", name: "深红", hex: "#D50000" },
    { code: "F5", name: "暗红", hex: "#B71C1C" },
    { code: "F6", name: "酒红", hex: "#880E4F" },
    { code: "G1", name: "浅肤", hex: "#FFE0B2" },
    { code: "G2", name: "肤色", hex: "#FFCC80" },
    { code: "G3", name: "深肤", hex: "#FFB74D" },
    { code: "G4", name: "沙色", hex: "#D7CCC8" },
    { code: "G5", name: "浅棕", hex: "#BCAAA4" },
    { code: "G6", name: "中棕", hex: "#A1887F" },
    { code: "G7", name: "深棕", hex: "#795548" },
    { code: "G8", name: "咖啡", hex: "#5D4037" },
    { code: "G9", name: "深咖", hex: "#3E2723" },
    { code: "H1", name: "白色", hex: "#FFFFFF" },
    { code: "H2", name: "浅灰", hex: "#F5F5F5" },
    { code: "H3", name: "银灰", hex: "#E0E0E0" },
    { code: "H4", name: "中灰", hex: "#9E9E9E" },
    { code: "H5", name: "深灰", hex: "#616161" },
    { code: "H6", name: "炭黑", hex: "#424242" },
    { code: "H7", name: "黑色", hex: "#000000" }
];

const PALETTES = {
    basic: PALETTE_BASIC,
    mard72: PALETTE_MARD_72,
    grayscale: [
        { code: "W", name: "白色", hex: "#FFFFFF" },
        { code: "LG", name: "浅灰", hex: "#D3D3D3" },
        { code: "G", name: "中灰", hex: "#808080" },
        { code: "DG", name: "深灰", hex: "#696969" },
        { code: "BK", name: "黑色", hex: "#000000" }
    ]
};

const PALETTE_LAB_CACHE = {};

function precomputeLab(paletteName) {
    if (PALETTE_LAB_CACHE[paletteName]) return PALETTE_LAB_CACHE[paletteName];
    const p = PALETTES[paletteName];
    const labs = p.map(c => {
        const rgb = hexToRgb(c.hex);
        const lab = rgbToLab(rgb.r, rgb.g, rgb.b);
        return { ...c, lab };
    });
    PALETTE_LAB_CACHE[paletteName] = labs;
    return labs;
}

function hexToRgb(hex) {
    var shorthandRegex = /^#?([a-f\d])([a-f\d])([a-f\d])$/i;
    hex = hex.replace(shorthandRegex, function(m, r, g, b) {
        return r + r + g + g + b + b;
    });
    var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    return result ? {
        r: parseInt(result[1], 16),
        g: parseInt(result[2], 16),
        b: parseInt(result[3], 16)
    } : null;
}

function rgbToLab(r, g, b) {
    let r1 = r / 255;
    let g1 = g / 255;
    let b1 = b / 255;

    r1 = (r1 > 0.04045) ? Math.pow((r1 + 0.055) / 1.055, 2.4) : r1 / 12.92;
    g1 = (g1 > 0.04045) ? Math.pow((g1 + 0.055) / 1.055, 2.4) : g1 / 12.92;
    b1 = (b1 > 0.04045) ? Math.pow((b1 + 0.055) / 1.055, 2.4) : b1 / 12.92;

    let x = (r1 * 0.4124 + g1 * 0.3576 + b1 * 0.1805) * 100;
    let y = (r1 * 0.2126 + g1 * 0.7152 + b1 * 0.0722) * 100;
    let z = (r1 * 0.0193 + g1 * 0.1192 + b1 * 0.9505) * 100;

    let x1 = x / 95.047;
    let y1 = y / 100.000;
    let z1 = z / 108.883;

    x1 = (x1 > 0.008856) ? Math.pow(x1, 1/3) : (7.787 * x1) + 16/116;
    y1 = (y1 > 0.008856) ? Math.pow(y1, 1/3) : (7.787 * y1) + 16/116;
    z1 = (z1 > 0.008856) ? Math.pow(z1, 1/3) : (7.787 * z1) + 16/116;

    return {
        l: (116 * y1) - 16,
        a: 500 * (x1 - y1),
        b: 200 * (y1 - z1)
    };
}

function getClosestColorLab(r, g, b, paletteLabs) {
    let minDistance = Infinity;
    let bestColor = paletteLabs[0];
    const currentLab = rgbToLab(r, g, b);
    for (const p of paletteLabs) {
        const targetLab = p.lab;
        const dL = currentLab.l - targetLab.l;
        const da = currentLab.a - targetLab.a;
        const db = currentLab.b - targetLab.b;
        const distance = dL * dL + da * da + db * db;
        if (distance < minDistance) {
            minDistance = distance;
            bestColor = p;
        }
    }
    return bestColor;
}

const syncWidth = () => {
    if (gridWidth.value < 10) gridWidth.value = 10;
    if (gridWidth.value > 150) gridWidth.value = 150;
};

const onImageUpload = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    if (!file.type.startsWith('image/')) {
        customAlert('请上传有效的图片文件！');
        return;
    }
    uploadLabelText.value = file.name;
    const reader = new FileReader();
    reader.onload = (event) => {
        const img = new Image();
        img.onload = () => {
            originalImage.value = img;
            processImage();
        };
        img.onerror = () => customAlert('图片加载失败，请重试。');
        img.src = event.target.result;
    };
    reader.readAsDataURL(file);
};

const processImage = () => {
    if (!originalImage.value) {
        customAlert('请先选择一张图片！');
        return;
    }

    const img = originalImage.value;
    const width = gridWidth.value;
    const height = Math.round(width * (img.height / img.width));

    const offCanvas = document.createElement('canvas');
    offCanvas.width = width;
    offCanvas.height = height;
    const offCtx = offCanvas.getContext('2d');
    
    offCtx.filter = 'saturate(1.2) contrast(1.1)';
    offCtx.drawImage(img, 0, 0, width, height);
    offCtx.filter = 'none';
    
    const imageData = offCtx.getImageData(0, 0, width, height);
    const data = imageData.data;
    
    const currentPaletteLabs = precomputeLab(palette.value);
    const resultColors = [];
    const resultObjects = [];

    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            const idx = (y * width + x) * 4;
            const r = data[idx];
            const g = data[idx + 1];
            const b = data[idx + 2];
            const a = data[idx + 3];

            let bestMatch;
            if (a < 128) {
                bestMatch = { code: "-", name: "背景", hex: "#FFFFFF" };
            } else {
                bestMatch = getClosestColorLab(r, g, b, currentPaletteLabs);
            }
            resultColors.push(bestMatch.hex);
            resultObjects.push(bestMatch);
        }
    }

    quantizedResult.value = {
        colors: resultColors,
        colorObjects: resultObjects,
        width: width,
        height: height
    };

    nextTick(() => {
        renderToCanvas(resultObjects, width, height);
        updateStats(resultObjects);
        processBtnText.value = "重新生成";
    });
};

const renderToCanvas = (colorObjects, gridW, gridH) => {
    if (!mainCanvas.value) return;
    const canvas = mainCanvas.value;
    const ctx = canvas.getContext('2d');
    const blockSize = 20;
    
    canvas.width = gridW * blockSize;
    canvas.height = gridH * blockSize;

    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.font = "10px sans-serif";
    ctx.textAlign = "center";
    ctx.textBaseline = "middle";

    for (let y = 0; y < gridH; y++) {
        for (let x = 0; x < gridW; x++) {
            const index = y * gridW + x;
            const obj = colorObjects[index];
            
            ctx.fillStyle = obj.hex;
            ctx.fillRect(x * blockSize, y * blockSize, blockSize, blockSize);

            if (obj.code !== '-') {
                const rgb = hexToRgb(obj.hex);
                const brightness = (rgb.r * 299 + rgb.g * 587 + rgb.b * 114) / 1000;
                ctx.fillStyle = brightness > 128 ? "rgba(0,0,0,0.6)" : "rgba(255,255,255,0.8)";
                ctx.fillText(obj.code, x * blockSize + blockSize/2, y * blockSize + blockSize/2);
            }
        }
    }

    if (showGrid.value) {
        drawGridLines(ctx, gridW, gridH, blockSize, canvas.width, canvas.height);
    }
};

const drawGridLines = (context, gridW, gridH, blockSize, w, h) => {
    context.strokeStyle = '#e0e0e0';
    context.lineWidth = 1;
    for (let x = 0; x <= gridW; x++) {
        context.beginPath();
        context.moveTo(x * blockSize, 0);
        context.lineTo(x * blockSize, h);
        if (x % 10 === 0) {
            context.strokeStyle = '#999';
            context.lineWidth = 1.5;
            context.stroke();
            context.lineWidth = 1;
            context.strokeStyle = '#e0e0e0';
        } else {
            context.stroke();
        }
    }
    for (let y = 0; y <= gridH; y++) {
        context.beginPath();
        context.moveTo(0, y * blockSize);
        context.lineTo(w, y * blockSize);
         if (y % 10 === 0) {
            context.strokeStyle = '#999';
            context.lineWidth = 1.5;
            context.stroke();
            context.lineWidth = 1;
            context.strokeStyle = '#e0e0e0';
        } else {
            context.stroke();
        }
    }
};

const updateStats = (colorObjects) => {
    const counts = {};
    colorObjects.forEach(obj => {
        if (obj.code === "-") return;
        const key = obj.code; 
        if (!counts[key]) {
            counts[key] = { count: 0, ...obj };
        }
        counts[key].count++;
    });

    const sortedKeys = Object.keys(counts).sort((a, b) => {
        return a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' });
    });

    statsData.value = sortedKeys.map(k => counts[k]);
};

const onCanvasMouseMove = (e) => {
    if (!quantizedResult.value || !mainCanvas.value) return;
    const canvas = mainCanvas.value;
    const rect = canvas.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    
    const canvasX = x * scaleX;
    const canvasY = y * scaleY;
    const blockSize = 20; 
    const gridX = Math.floor(canvasX / blockSize);
    const gridY = Math.floor(canvasY / blockSize);
    
    const { width, height, colorObjects } = quantizedResult.value;
    
    if (gridX >= 0 && gridX < width && gridY >= 0 && gridY < height) {
        const index = gridY * width + gridX;
        const colorObj = colorObjects[index];
        
        if (colorObj && colorObj.code !== '-') {
            tooltipHtml.value = `
                <span style="display:inline-block;width:10px;height:10px;background:${colorObj.hex};border:1px solid #fff;margin-right:5px;"></span>
                <strong>${colorObj.code}</strong> ${colorObj.name}
            `;
            tooltipVisible.value = true;
            tooltipStyle.value = {
                position: 'fixed',
                left: `${e.clientX + 15}px`,
                top: `${e.clientY + 15}px`
            };
        } else {
            tooltipVisible.value = false;
        }
    } else {
        tooltipVisible.value = false;
    }
};

const onCanvasMouseLeave = () => {
    tooltipVisible.value = false;
};

const downloadFullSheet = () => {
    if (!quantizedResult.value) return;
    const { colorObjects, width, height } = quantizedResult.value;
    const blockSize = 30; 
    
    const counts = {};
    colorObjects.forEach(obj => {
        if (obj.code === "-") return;
        if (!counts[obj.code]) counts[obj.code] = { ...obj, count: 0 };
        counts[obj.code].count++;
    });
    const sortedKeys = Object.keys(counts).sort((a,b) => a.localeCompare(b, undefined, {numeric: true, sensitivity: 'base'})); 
    
    const cols = 4; 
    const rows = Math.ceil(sortedKeys.length / cols);
    const legendItemHeight = 40;
    const legendPadding = 20;
    const legendHeight = rows * legendItemHeight + legendPadding * 2 + 50; 

    const canvasW = width * blockSize;
    const canvasH = height * blockSize + legendHeight;
    
    const c = document.createElement('canvas');
    c.width = canvasW;
    c.height = canvasH;
    const cx = c.getContext('2d');

    cx.fillStyle = "#FFFFFF";
    cx.fillRect(0, 0, canvasW, canvasH);

    cx.font = "12px Arial";
    cx.textAlign = "center";
    cx.textBaseline = "middle";

    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            const index = y * width + x;
            const obj = colorObjects[index];
            
            cx.fillStyle = obj.hex;
            cx.fillRect(x * blockSize, y * blockSize, blockSize, blockSize);

            if (obj.code !== '-') {
                const rgb = hexToRgb(obj.hex);
                const brightness = (rgb.r * 299 + rgb.g * 587 + rgb.b * 114) / 1000;
                cx.fillStyle = brightness > 128 ? "#000" : "#fff";
                cx.fillText(obj.code, x * blockSize + blockSize/2, y * blockSize + blockSize/2);
            }
        }
    }

    if (showGrid.value) {
        drawGridLines(cx, width, height, blockSize, width * blockSize, height * blockSize);
    }

    const legendStartY = height * blockSize;
    
    cx.beginPath();
    cx.moveTo(0, legendStartY);
    cx.lineTo(canvasW, legendStartY);
    cx.strokeStyle = "#333";
    cx.lineWidth = 2;
    cx.stroke();

    cx.fillStyle = "#333";
    cx.font = "bold 20px Arial";
    cx.textAlign = "left";
    cx.fillText("拼豆用量清单", 20, legendStartY + 35);

    cx.font = "14px Arial";
    const itemW = (canvasW - legendPadding * 2) / cols;
    
    sortedKeys.forEach((key, index) => {
        const item = counts[key];
        const row = Math.floor(index / cols);
        const col = index % cols;
        
        const x = legendPadding + col * itemW;
        const y = legendStartY + 60 + row * legendItemHeight;

        cx.fillStyle = item.hex;
        cx.fillRect(x, y, 20, 20);
        cx.strokeStyle = "#ccc";
        cx.strokeRect(x, y, 20, 20);

        cx.fillStyle = "#333";
        cx.textAlign = "left";
        cx.fillText(`[${item.code}] ${item.name} x ${item.count}`, x + 30, y + 15);
    });

    if (window.plus) {
        try {
            window.plus.nativeUI.showWaiting("正在保存...");
            const base64Data = c.toDataURL('image/png');
            const bitmap = new window.plus.nativeObj.Bitmap("pindou_pattern");
            bitmap.loadBase64Data(base64Data, function() {
                const fileName = "_doc/pindou_" + Date.now() + ".png";
                bitmap.save(fileName, {overwrite: true, format: "png"}, function(i) {
                    window.plus.gallery.save(fileName, function() {
                        window.plus.nativeUI.closeWaiting();
                        window.plus.nativeUI.toast("已保存到相册");
                        bitmap.clear();
                    }, function(e) {
                        window.plus.nativeUI.closeWaiting();
                        window.plus.nativeUI.toast("保存到相册失败: " + (e.message || e));
                        bitmap.clear();
                    });
                }, function(e) {
                    window.plus.nativeUI.closeWaiting();
                    window.plus.nativeUI.toast("保存临时文件失败: " + (e.message || e));
                    bitmap.clear();
                });
            }, function(e) {
                window.plus.nativeUI.closeWaiting();
                window.plus.nativeUI.toast("加载图片数据失败: " + (e.message || e));
                bitmap.clear();
            });
        } catch (e) {
            if(window.plus) window.plus.nativeUI.closeWaiting();
            if(window.plus) window.plus.nativeUI.toast("发生错误: " + e.message);
        }
        return;
    }

    const link = document.createElement('a');
    link.download = `pixel-pattern-${Date.now()}.png`;
    link.href = c.toDataURL();
    link.click();
};
</script>

<style scoped>
.pindou-container {
    min-height: 100vh;
    background-color: var(--bg-color-light, #f5f7fa);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    color: var(--text-color-light, #2f3542);
}

:global(body.dark-theme) .pindou-container {
    background: #121212;
}

.home-header {
    position: sticky;
    top: 0;
    z-index: 100;
    display: flex;
    align-items: center;
    padding: 15px 20px;
    background: var(--header-bg, rgba(255, 255, 255, 0.9));
    backdrop-filter: blur(10px);
    box-shadow: 0 2px 10px var(--shadow-color, rgba(0, 0, 0, 0.05));
    border-bottom-left-radius: 16px;
    border-bottom-right-radius: 16px;
    color: var(--text-color-light, #333);
}

:global(body.dark-theme) .home-header {
    background: rgba(31, 31, 31, 0.85);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
    color: #e0e0e0;
}

.header-back {
    font-size: 24px;
    margin-right: 15px;
    cursor: pointer;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--nav-btn-active-color, #4f8cff);
}

.header-title {
    font-size: 20px;
    font-weight: bold;
    flex-grow: 1;
}

.container {
    padding: 10px;
}

main {
    padding-top: 10px;
    padding-bottom: 20px;
    width: 100%;
}

.controls {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    justify-content: center;
    align-items: center; 
    margin-bottom: 20px;
    padding: 20px;
    background-color: var(--bg-color-card, #fff);
    border-radius: 12px;
    box-shadow: 0 4px 12px var(--shadow-color, rgba(0,0,0,0.05));
}

:global(body.dark-theme) .controls {
    background-color: #1f1f1f;
}

.control-group {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
    width: 100%;
    max-width: 300px;
}

.control-group label {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--text-color-medium, #2f3542);
}
:global(body.dark-theme) .control-group label {
    color: #bbb;
}

.range-input-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
}

input[type="file"] { display: none; }

.custom-file-upload {
    border: 2px dashed var(--nav-btn-active-color, #4f8cff);
    color: var(--nav-btn-active-color, #4f8cff);
    padding: 10px 15px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 600;
    background: transparent;
    text-align: center;
    width: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 40px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    box-sizing: border-box;
}

.custom-file-upload:hover {
    background-color: var(--nav-btn-active-color, #4f8cff);
    color: white;
}

input[type="range"] {
    flex-grow: 1;
    cursor: pointer;
    height: 40px;
    margin: 0;
}

input[type="number"], select {
    width: 70px;
    height: 40px;
    padding: 5px;
    border: 1px solid var(--border-color, #ced6e0);
    border-radius: 6px;
    font-size: 0.95rem;
    text-align: center;
    background-color: var(--bg-color-light, #fff);
    color: var(--text-color-light, #333);
    box-sizing: border-box;
}
select { width: 100%; padding: 0 12px; }

:global(body.dark-theme) input[type="number"],
:global(body.dark-theme) select {
    background-color: #2a2a2a;
    border-color: #444;
    color: #e0e0e0;
}

.checkbox-group {
    flex-direction: row;
    align-items: center;
    gap: 10px;
    height: 40px;
    padding-top: 24px;
}

input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; }

button {
    padding: 0 20px;
    height: 40px;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 100%;
    max-width: 300px;
    margin-top: 24px;
    box-sizing: border-box;
}

.primary-btn {
    background-color: var(--nav-btn-active-color, #4f8cff);
    color: white;
}
.secondary-btn {
    background-color: var(--text-color-medium, #8ca2c7);
    color: white;
}
.secondary-btn:disabled { background-color: #bdc3c7; cursor: not-allowed; }

.preview-area {
    display: flex; flex-direction: column; align-items: center; gap: 15px; width: 100%;
}

.canvas-wrapper {
    position: relative;
    width: 100%;
    border: 1px solid var(--border-color, #dfe4ea);
    border-radius: 8px;
    padding: 10px;
    background-color: var(--bg-color-light, #eee);
    box-shadow: inset 0 0 10px rgba(0,0,0,0.05);
    display: flex; justify-content: center; align-items: center; min-height: 200px;
    box-sizing: border-box;
}

:global(body.dark-theme) .canvas-wrapper {
    background-color: #2a2a2a;
    border-color: #444;
}

canvas {
    image-rendering: pixelated;
    width: 100%; height: auto; max-width: 100%; cursor: crosshair; background-color: white; margin: auto; box-shadow: 0 0 15px rgba(0,0,0,0.1);
}

.empty-state { text-align: center; color: #a4b0be; display: flex; flex-direction: column; align-items: center; gap: 10px; }
.empty-icon { font-size: 3rem; opacity: 0.5; }

.tooltip {
    position: fixed; background-color: rgba(0, 0, 0, 0.85); color: white; padding: 6px 10px; border-radius: 4px; font-size: 0.8rem; pointer-events: none; opacity: 0; transform: translateY(10px); transition: opacity 0.2s ease, transform 0.2s ease; z-index: 9999; white-space: nowrap; box-shadow: 0 4px 8px rgba(0,0,0,0.3);
}
.tooltip.visible { opacity: 1; transform: translateY(0); }

.stats-panel {
    width: 100%; background-color: var(--bg-color-card, #fff); padding: 15px; border-radius: 12px; margin-top: 10px; box-sizing: border-box; box-shadow: 0 4px 12px var(--shadow-color, rgba(0,0,0,0.05));
}
:global(body.dark-theme) .stats-panel { background-color: #1f1f1f; }

.stats-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid var(--border-color, #dfe4ea); padding-bottom: 10px; }
:global(body.dark-theme) .stats-header { border-bottom-color: #333; }
.stats-header h3 { font-size: 1.1rem; margin: 0; color: var(--text-color-light, #333); }
:global(body.dark-theme) .stats-header h3 { color: #e0e0e0; }

#colorList { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 8px; list-style: none; padding: 0; margin: 0; }
#colorList li { display: flex; align-items: center; gap: 8px; font-size: 0.85rem; padding: 6px; background: var(--bg-color-light, #f9f9f9); border-radius: 6px; box-shadow: 0 2px 4px var(--shadow-color, rgba(0,0,0,0.05)); }
:global(body.dark-theme) #colorList li { background: #2a2a2a; }

.color-swatch { width: 20px; height: 20px; border-radius: 50%; border: 1px solid rgba(0,0,0,0.1); display: inline-block; flex-shrink: 0; }
.color-info { display: flex; flex-direction: column; flex-grow: 1; line-height: 1.2; overflow: hidden; }
.color-code { font-weight: 700; color: var(--nav-btn-active-color, #4f8cff); font-size: 0.9rem; }
.color-name { color: var(--text-color-medium, #747d8c); font-size: 0.75rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
:global(body.dark-theme) .color-name { color: #bbb; }
.color-count { font-size: 0.85rem; color: var(--text-color-medium, #2f3542); white-space: nowrap; }
:global(body.dark-theme) .color-count { color: #e0e0e0; }

@media (min-width: 900px) {
    .controls { flex-direction: row; align-items: flex-start; }
    .control-group { width: auto; min-width: 150px; }
    .checkbox-group { padding-top: 24px; }
    button { width: auto; margin-top: 24px; }
    #colorList { grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); }
}
</style>