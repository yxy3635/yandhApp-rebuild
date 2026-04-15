/**
 * 5+ App：读取安装包版本名、下载 APK、触发安装。
 */

export function getAppVersionName() {
  return new Promise((resolve) => {
    if (typeof plus === 'undefined' || !plus.runtime?.getProperty) {
      resolve('');
      return;
    }
    try {
      plus.runtime.getProperty(plus.runtime.appid, (inf) => {
        resolve((inf && inf.version) ? String(inf.version) : '');
      });
    } catch {
      resolve('');
    }
  });
}

/**
 * @param {string} url
 * @param {(percent: number, downloaded: number, total: number) => void} onProgress
 * @returns {Promise<string>} 本地文件路径
 */
export function downloadApk(url, onProgress) {
  return new Promise((resolve, reject) => {
    if (typeof plus === 'undefined' || !plus.downloader?.createDownload) {
      reject(new Error('NO_PLUS'));
      return;
    }

    const dtask = plus.downloader.createDownload(
      url,
      {},
      (d, status) => {
        if (status === 200) {
          resolve(d.filename);
        } else {
          reject(new Error(`下载失败 (${status})`));
        }
      }
    );

    dtask.addEventListener(
      'statechanged',
      (task) => {
        try {
          if (task.state === 3 && task.totalSize > 0) {
            const p = Math.floor((task.downloadedSize / task.totalSize) * 100);
            onProgress?.(Math.min(100, p), task.downloadedSize, task.totalSize);
          }
        } catch (_) {}
      },
      false
    );

    dtask.start();
  });
}

export function installApkFile(path) {
  return new Promise((resolve, reject) => {
    if (typeof plus === 'undefined' || !plus.runtime?.install) {
      reject(new Error('NO_PLUS'));
      return;
    }
    plus.runtime.install(
      path,
      {},
      () => resolve(),
      (e) => reject(e || new Error('安装失败'))
    );
  });
}
