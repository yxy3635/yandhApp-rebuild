/**
 * 农历公历转换库
 * 基于网络上开源算法，适用于1900-2100年
 */

export class LunarCalendar {
  constructor() {
    this.lunarInfo = [
      0x04bd8,0x04ae0,0x0a570,0x054d5,0x0d260,0x0d950,0x16554,0x056a0,0x09ad0,0x055d2,
      0x04ae0,0x0a5b6,0x0a4d0,0x0d250,0x1d255,0x0b540,0x0d6a0,0x0ada2,0x095b0,0x14977,
      0x04970,0x0a4b0,0x0b4b5,0x06a50,0x06d40,0x1ab54,0x02b60,0x09570,0x052f2,0x04970,
      0x06566,0x0d4a0,0x0ea50,0x06e95,0x05ad0,0x02b60,0x186e3,0x092e0,0x1c8d7,0x0c950,
      0x0d4a0,0x1d8a6,0x0b550,0x056a0,0x1a5b4,0x025d0,0x092d0,0x0d2b2,0x0a950,0x0b557,
      0x06ca0,0x0b550,0x15355,0x04da0,0x0a5b0,0x14573,0x052b0,0x0a9a8,0x0e950,0x06aa0,
      0x0aea6,0x0ab50,0x04b60,0x0aae4,0x0a570,0x05260,0x0f263,0x0d950,0x05b57,0x056a0,
      0x096d0,0x04dd5,0x04ad0,0x0a4d0,0x0d4d4,0x0d250,0x0d558,0x0b540,0x0b6a0,0x195a6,
      0x095b0,0x049b0,0x0a974,0x0a4b0,0x0b27a,0x06a50,0x06d40,0x0af46,0x0ab60,0x09570,
      0x04af5,0x04970,0x064b0,0x074a3,0x0ea50,0x06b58,0x055c0,0x0ab60,0x096d5,0x092e0,
      0x0c960,0x0d954,0x0d4a0,0x0da50,0x07552,0x056a0,0x0abb7,0x025d0,0x092d0,0x0cab5,
      0x0a950,0x0b4a0,0x0baa4,0x0ad50,0x055d9,0x04ba0,0x0a5b0,0x15176,0x052b0,0x0a930,
      0x07954,0x06aa0,0x0ad50,0x05b52,0x04b60,0x0a6e6,0x0a4e0,0x0d260,0x0ea65,0x0d530,
      0x05aa0,0x076a3,0x096d0,0x04afb,0x04ad0,0x0a4d0,0x1d0b6,0x0d250,0x0d520,0x0dd45,
      0x0b5a0,0x056d0,0x055b2,0x049b0,0x0a577,0x0a4b0,0x0aa50,0x1b255,0x06d20,0x0ada0,
      0x14b63,0x09370,0x049f8,0x04970,0x064b0,0x168a6,0x0ea50,0x06b20,0x1a6c4,0x0aae0,
      0x0a2e0,0x0d2e3,0x0c960,0x0d557,0x0d4a0,0x0da50,0x05d55,0x056a0,0x0a6d0,0x055d4,
      0x052d0,0x0a9b8,0x0a950,0x0b4a0,0x0b6a6,0x0ad50,0x055a0,0x0aba4,0x0a5b0,0x052b0,
      0x0b273,0x06930,0x07337,0x06aa0,0x0ad50,0x14b55,0x04b60,0x0a570,0x054e4,0x0d160,
      0x0e968,0x0d520,0x0daa0,0x16aa6,0x056d0,0x04ae0,0x0a9d4,0x0a2d0,0x0d150,0x0f252,
      0x0d520];

    this.nStr1 = ['正','二','三','四','五','六','七','八','九','十','十一','十二'];
    this.nStr2 = ['初','十','廿','卅'];
    this.nStr3 = ['一','二','三','四','五','六','七','八','九','十'];
    this.solarMonth = [31,28,31,30,31,30,31,31,30,31,30,31];
  }

  lYearDays(y) {
    let i; let sum = 348;
    for(i = 0x8000; i > 0x8; i >>= 1) {
      sum += (this.lunarInfo[y-1900] & i) ? 1 : 0;
    }
    return sum + this.leapDays(y);
  }

  leapDays(y) {
    if(this.leapMonth(y)) {
      return (this.lunarInfo[y-1900] & 0x10000) ? 30 : 29;
    }
    return 0;
  }

  leapMonth(y) {
    return this.lunarInfo[y-1900] & 0xf;
  }

  monthDays(y, m) {
    return (this.lunarInfo[y-1900] & (0x10000>>m)) ? 30 : 29;
  }

  isLeapYear(y) {
    return (y % 4 === 0 && y % 100 !== 0) || (y % 400 === 0);
  }

  solar2lunar(y, m, d) {
    if(y < 1900 || y > 2100) {
      return false;
    }
    if(y === 1900 && m === 1 && d < 31) {
      return false;
    }
    let objDate;
    if(typeof y === 'object') {
      objDate = y;
    } else {
      objDate = new Date(y, m-1, d);
    }
    let temp = 0;
    const baseDate = new Date(1900, 0, 31);
    let offset = Math.floor((objDate - baseDate) / 86400000);
    let lYear = 1900;
    while(lYear < 2101 && offset > 0) {
      temp = this.lYearDays(lYear);
      offset -= temp;
      lYear++;
    }
    if(offset < 0) {
      offset += temp;
      lYear--;
    }
    let lMonth = 1;
    const leapMonth = this.leapMonth(lYear);
    let isLeap = false;
    while(lMonth < 13 && offset > 0) {
      if(leapMonth > 0 && lMonth === (leapMonth + 1) && isLeap === false) {
        --lMonth;
        isLeap = true;
        temp = this.leapDays(lYear);
      } else {
        temp = this.monthDays(lYear, lMonth);
      }
      if(isLeap === true && lMonth === (leapMonth + 1)) {
        isLeap = false;
      }
      offset -= temp;
      if(isLeap === false) {
        lMonth++;
      }
    }
    if(offset === 0 && leapMonth > 0 && lMonth === leapMonth + 1) {
      if(isLeap) {
        isLeap = false;
      } else {
        isLeap = true;
        --lMonth;
      }
    }
    if(offset < 0) {
      offset += temp;
      --lMonth;
    }
    return {
      lYear: lYear,
      lMonth: lMonth,
      lDay: offset + 1,
      isLeap: isLeap,
      lMonthCn: this.nStr1[lMonth-1],
      lDayCn: this.getChinaDay(offset + 1),
      cYear: objDate.getFullYear(),
      cMonth: objDate.getMonth() + 1,
      cDay: objDate.getDate()
    };
  }

  lunar2solar(y, m, d, isLeap = false) {
    if(y < 1900 || y > 2100) {
      return false;
    }
    const leapMonth = this.leapMonth(y);
    let offset = 0;
    for(let i = 1900; i < y; i++) {
      offset += this.lYearDays(i);
    }
    for(let i = 1; i < m; i++) {
      if(leapMonth > 0 && i === leapMonth) {
        offset += this.leapDays(y);
      }
      offset += this.monthDays(y, i);
    }
    if(isLeap && leapMonth === m) {
      offset += this.monthDays(y, m);
    }
    offset += d;
    const baseDate = new Date(1900, 0, 31);
    const resultDate = new Date(baseDate.getTime() + (offset - 1) * 86400000);
    return {
      cYear: resultDate.getFullYear(),
      cMonth: resultDate.getMonth() + 1,
      cDay: resultDate.getDate(),
      lYear: y,
      lMonth: m,
      lDay: d,
      isLeap: isLeap
    };
  }

  getChinaDay(d) {
    let s;
    switch (d) {
      case 10:
        s = '初十'; break;
      case 20:
        s = '二十'; break;
      case 30:
        s = '三十'; break;
      default:
        s = this.nStr2[Math.floor(d/10)];
        s += this.nStr3[d%10-1];
    }
    return s;
  }

  getLunarDateInYear(targetYear, lunarMonth, lunarDay, isLeap = false) {
    return this.lunar2solar(targetYear, lunarMonth, lunarDay, isLeap);
  }

  formatLunarDate(y, m, d, isLeap = false) {
    const monthStr = isLeap ? `闰${this.nStr1[m-1]}月` : `${this.nStr1[m-1]}月`;
    const dayStr = this.getChinaDay(d);
    return `农历${y}年${monthStr}${dayStr}`;
  }
}

export const lunarCalendar = new LunarCalendar();
