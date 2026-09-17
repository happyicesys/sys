/**
 * The smart freezer's status snapshot, as an object with opinions.
 *
 * The panel used to read the raw JSON inline (`s?.door?.lockState ?? '—'`, `t.compressorRemoteMode
 * === true ? 'warn' : 'neutral'`), which put the meaning of every field in the template and let the
 * status grid and the control rows drift apart. Both now ask this class, so "what is the fan doing"
 * and "what colour is that" have one answer each.
 *
 * Shape of the payload: FreezerControlController::show -> `status`, written by the APK's
 * FREEZERCTLACK. Every field is optional — an old build, a host that never bound, or a controller
 * off the bus all send less — so every getter degrades to "unknown" instead of throwing.
 */

/** Chip / tile colours. Grey means "not known", never "off" — see StateChip. */
export const TONE = {
    OK: 'ok',
    ON: 'on',
    OFF: 'off',
    INFO: 'info',
    WARN: 'warn',
    BAD: 'bad',
    UNKNOWN: 'unknown',
};

/** on / off / — for a tri-state flag. */
export function onOff(value) {
    return value === true ? 'on' : value === false ? 'off' : '—';
}

/** Green when on, slate when off, grey when the machine never said. */
export function boolTone(value) {
    return value === true ? TONE.ON : value === false ? TONE.OFF : TONE.UNKNOWN;
}

/**
 * Who is driving the compressor or the fan. "remote (us)" is amber on purpose: it is a state
 * someone has to hand back, or the controller never cycles the cabinet again.
 */
export function modeText(remote) {
    return remote === true ? 'remote (us)' : remote === false ? 'controller' : '—';
}

export function modeTone(remote) {
    return remote === true ? TONE.WARN : remote === false ? TONE.INFO : TONE.UNKNOWN;
}

/** Seconds as the machine's own cadence reads: "60 s" -> "1 min". */
export function everySeconds(seconds) {
    if (!seconds) return '—';
    return seconds % 60 === 0 ? `${seconds / 60} min` : `${seconds} s`;
}

/** The chamber reading and, when there isn't one, the reason in a technician's words. */
class ChamberReading {
    constructor(thermostat) {
        this.t = thermostat || {};
    }

    get celsius() {
        const value = Number(this.t.celsius);
        return this.t.available && Number.isFinite(value) ? value : null;
    }

    get trustworthy() {
        return this.celsius !== null && this.t.connected && this.t.communicating && this.t.sensorOk;
    }

    get text() {
        return this.celsius === null ? '—' : `${this.celsius.toFixed(1)} °C`;
    }

    /** What the number means, or why there is none. */
    get detail() {
        if (!this.t.available) return 'Thermostat not reported by the host plugin';
        if (!this.t.connected) return 'Thermostat port closed';
        if (!this.t.communicating) return 'Thermostat not answering';
        if (!this.t.sensorOk) return 'Probe fault';
        return `${this.t.model || 'Thermostat'} · probe OK`;
    }

    /**
     * Ice cream holds at −18 °C or colder, so the hero tile is green there, amber on the way up and
     * red once the stock is at risk. An untrustworthy reading is never coloured as if it were fine.
     */
    get tone() {
        if (!this.trustworthy) return TONE.UNKNOWN;
        if (this.celsius <= -18) return TONE.OK;
        if (this.celsius <= -12) return TONE.WARN;
        return TONE.BAD;
    }
}

export class FreezerStatus {
    static from(raw) {
        return new FreezerStatus(raw);
    }

    constructor(raw) {
        this.raw = raw || null;
    }

    get known() {
        return this.raw !== null;
    }

    get thermostat() {
        return this.raw?.thermostat || { available: false };
    }

    get chamber() {
        return new ChamberReading(this.thermostat);
    }

    get compressorOn() {
        return this.thermostat.compressorOn ?? null;
    }

    get compressorRemote() {
        return this.thermostat.compressorRemoteMode ?? null;
    }

    get fanOn() {
        return this.raw?.fanOn ?? null;
    }

    get fanRemote() {
        return this.thermostat.fanRemoteMode ?? null;
    }

    get lightState() {
        return this.raw?.lightState || null;
    }

    get volume() {
        return this.raw?.volume ?? null;
    }

    get door() {
        return this.raw?.door || {};
    }

    /** "locked · closed" for the door control row. */
    get doorText() {
        return `${this.door.lockState ?? '—'} · ${this.door.doorState ?? '—'}`;
    }

    get doorTone() {
        if (!this.door.lockState) return TONE.UNKNOWN;
        return this.door.lockState === 'unlocked' || this.door.doorState === 'opened' ? TONE.WARN : TONE.OK;
    }

    get alarmText() {
        if (!this.thermostat.available) return '—';
        if (this.thermostat.highTempAlarm) return 'HIGH temp';
        if (this.thermostat.lowTempAlarm) return 'LOW temp';
        return 'none';
    }

    get alarmTone() {
        if (this.alarmText === 'none') return TONE.OK;
        return this.alarmText === '—' ? TONE.UNKNOWN : TONE.BAD;
    }

    get telemetry() {
        return this.raw?.telemetry || null;
    }

    /** APK v14: who the machine is to Zijia, and the host/plugin versions that move under us. */
    get identity() {
        return this.raw?.identity || null;
    }

    /** 'present' | 'cut' | null — the box's mains feed as the driver board reports it. */
    get mains() {
        return this.raw?.power?.mains ?? null;
    }

    get humidity() {
        const v = Number(this.raw?.humidity);
        return Number.isFinite(v) ? v : null;
    }

    get watchdog() {
        return this.raw?.watchdog ?? null;
    }

    /** [{id, state, open, description}] as the host lists them; empty when not reported. */
    get cameras() {
        return Array.isArray(this.raw?.cameras) ? this.raw.cameras : [];
    }

    /** Relay duty and cycles from the machine's own thermostat samples (CompressorDutyMeter). */
    get compressorDuty() {
        return this.raw?.compressor || null;
    }

    /** Radar footfall, only once the machine has seen its radar fire (RadarCounter). */
    get radar() {
        return this.raw?.radar || null;
    }

    /**
     * A climbing duty is the earliest sign of a cabinet losing cold. Amber past 80 %, red at 95 %
     * once at least an hour of samples backs the number.
     */
    get dutyTone() {
        const d = this.compressorDuty;
        if (!d || (d.coveredMinutes ?? 0) < 60) return TONE.UNKNOWN;
        if (d.dutyPercent1h >= 95) return TONE.BAD;
        if (d.dutyPercent1h >= 80) return TONE.WARN;
        return TONE.OK;
    }

    /**
     * A command sent while the controller owns the compressor or the fan is accepted by the host and
     * changes nothing, so the panel says so before the button is pressed rather than after.
     */
    get ignoredControls() {
        const notes = [];
        if (this.compressorRemote === false) {
            notes.push('The temperature controller is running the compressor itself, so Compressor on/off is ignored until you press Remote.');
        }
        if (this.fanRemote === false) {
            notes.push("The fan follows the door switch on the controller, so Cabinet fan on/off is ignored. Only Zijia's portal can change that today.");
        }
        return notes;
    }

    /** The secondary status grid, in reading order. The hero chamber tile is rendered separately. */
    get tiles() {
        const bridge = this.raw?.bridge;
        const apk = this.raw?.apk;

        return [
            { label: 'Compressor', value: onOff(this.compressorOn), tone: boolTone(this.compressorOn) },
            { label: 'Compressor control', value: modeText(this.compressorRemote), tone: modeTone(this.compressorRemote) },
            { label: 'Cooling demand', value: onOff(this.thermostat.coolingDemand), tone: boolTone(this.thermostat.coolingDemand) },
            { label: 'Defrost', value: onOff(this.thermostat.defrosting), tone: this.thermostat.defrosting ? TONE.WARN : boolTone(this.thermostat.defrosting) },
            { label: 'Alarm', value: this.alarmText, tone: this.alarmTone },
            { label: 'Lock', value: this.door.lockState ?? '—', tone: this.door.lockState === 'locked' ? TONE.OK : this.door.lockState === 'unlocked' ? TONE.WARN : TONE.UNKNOWN },
            { label: 'Door', value: this.door.doorState ?? '—', tone: this.door.doorState === 'closed' ? TONE.OK : this.door.doorState === 'opened' ? TONE.WARN : TONE.UNKNOWN },
            { label: 'Lock link', value: this.door.lockOnlineState ?? '—', tone: this.door.lockOnlineState === 'online' ? TONE.OK : TONE.UNKNOWN },
            { label: 'Cabinet fan', value: onOff(this.fanOn), tone: boolTone(this.fanOn) },
            { label: 'Fan control', value: modeText(this.fanRemote), tone: modeTone(this.fanRemote) },
            { label: 'Light', value: this.lightState || 'not reported', tone: this.lightState ? TONE.INFO : TONE.UNKNOWN },
            { label: 'Volume', value: this.volume ?? '—', tone: this.volume === 0 ? TONE.WARN : this.volume === null ? TONE.UNKNOWN : TONE.INFO },
            {
                label: 'Host bridge',
                value: bridge ? (bridge.bound ? (bridge.hostReady ? 'ready' : 'bound, not ready') : 'not bound') : '—',
                tone: bridge?.hostReady ? TONE.OK : bridge ? TONE.BAD : TONE.UNKNOWN,
            },
            { label: 'Sale in progress', value: this.raw?.saleInProgress ? 'yes' : 'no', tone: this.raw?.saleInProgress ? TONE.WARN : TONE.OFF },
            { label: 'App', value: apk ? `v${apk.versionName} (${apk.versionCode})` : '—', tone: apk ? TONE.INFO : TONE.UNKNOWN },
            ...this.batch2Tiles,
        ];
    }

    /** Tiles that only a v14+ app fills; omitted entirely on older builds so the grid does not show a row of dashes. */
    get batch2Tiles() {
        if (!this.raw?.identity && !this.raw?.power && !this.cameras.length && !this.compressorDuty && !this.radar) return [];
        const duty = this.compressorDuty;
        const cams = this.cameras;
        const open = cams.filter((c) => c.open === true).length;
        return [
            { label: 'Mains', value: this.mains === 'present' ? 'present' : this.mains === 'cut' ? 'CUT — on battery' : '—', tone: this.mains === 'present' ? TONE.OK : this.mains === 'cut' ? TONE.BAD : TONE.UNKNOWN },
            {
                label: 'Compressor duty (1 h)',
                value: duty ? `${duty.dutyPercent1h}% · ${duty.cycles1h} cycle${duty.cycles1h === 1 ? '' : 's'}` : '—',
                tone: this.dutyTone,
            },
            {
                label: 'Cameras',
                value: cams.length ? `${open}/${cams.length} open` : '—',
                tone: !cams.length ? TONE.UNKNOWN : open === cams.length ? TONE.OK : TONE.BAD,
            },
            { label: 'Watchdog', value: this.watchdog ?? '—', tone: this.watchdog ? TONE.INFO : TONE.UNKNOWN },
            ...(this.radar
                ? [{ label: 'Footfall (radar)', value: `${this.radar.enters1h} in 1 h · ${this.radar.enters24h} in 24 h`, tone: TONE.INFO }]
                : []),
            ...(this.humidity !== null ? [{ label: 'Humidity', value: `${Math.round(this.humidity)}%`, tone: TONE.INFO }] : []),
        ];
    }
}
