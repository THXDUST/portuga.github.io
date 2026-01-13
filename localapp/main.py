import customtkinter as ctk
import os
import dotenv
import psycopg2
import traceback
import json
import threading
import time
import sqlite3
import platform
import sys
from psycopg2.extras import RealDictCursor
from customtkinter import CTk as CTK
from pathlib import Path
from datetime import datetime, timedelta
from dateutil import tz
from typing import Tuple, List, Dict, Any

# Optional notification libs
try:
    from win10toast import ToastNotifier
except Exception:
    ToastNotifier = None

try:
    from plyer import notification as plyer_notification
except Exception:
    plyer_notification = None

# Optional websocket client
try:
    import websocket
except Exception:
    websocket = None

# -----------------------
# Configuration / Globals
# -----------------------
LOGS_DIR = "./logs"
PEDIDOS_DIR = "C:/Datacaixa/Integracao/Pedidos"
PROCESSED_FILE = "./processed_orders.json"
OFFLINE_DB = "./offline_queue.db"
SETTINGS_FILE = "./settings.json"
dotenv.load_dotenv()
ORDER_INDEX = 1

DEFAULT_POLL_INTERVAL = 5
DEFAULT_THEME = "light"

THEME_PALETTE = {
    "dark": {
        "appearance": "Dark",
        "window_bg": "#2b2a22",
        "primary": "#e8c13f",
        "primary_dark": "#caa92f",
        "accent": "#f4da78",
        "text": "#f8f4e6",
        "muted_text": "#d9c98b",
        "button_text": "#1b1200",
        "status_success": "#7be87b",
        "status_error": "#ff8a8a",
        "log_bg": "#1f1d18",
        "log_text": "#efe8cf",
    },
    "light": {
        "appearance": "Light",
        "window_bg": "#fff8e6",
        "primary": "#e8c13f",
        "primary_dark": "#caa92f",
        "accent": "#f6e7a8",
        "text": "#2b2200",
        "muted_text": "#5b4b00",
        "button_text": "#ffffff",
        "status_success": "#0a7f0a",
        "status_error": "#a80000",
        "log_bg": "#fff8e6",
        "log_text": "#2b2200",
    },
}

DEFAULT_CUSTOMER = {
    "full_name": "Cliente",
    "phone_number": "11 98765-4321",
    "cep": "123456-78",
    "address_street": "R. dos Tolos",
    "address_number": "0",
    "address_complement": "Casa 1",
    "address_neighborhood": "Tolos",
    "address_city": "Galinha",
    "address_state": "SP",
}

DEFAULT_SETTINGS = {
    "theme": DEFAULT_THEME,
    "poll_interval": DEFAULT_POLL_INTERVAL,
    "auto_sync": False,
    "ws_url": "",              # WebSocket URL if used (ws:// or wss://)
    "notify_windows": True,
    "mark_exported_in_db": True,  # try to mark exported in DB
}

def load_settings() -> dict:
    if os.path.exists(SETTINGS_FILE):
        try:
            with open(SETTINGS_FILE, "r", encoding="utf-8") as f:
                s = json.load(f)
                DEFAULT_SETTINGS.update(s)
        except Exception:
            pass
    return DEFAULT_SETTINGS.copy()

def get_path_mei(rel_path: str):
    abs_path = rel_path
    if getattr(sys, "frozen", False):
        abs_path = sys._MEIPASS
    else:
        abs_path = os.path.dirname(os.path.abspath(__file__))

    path = os.path.join(abs_path, rel_path)
    return path

dotenv.load_dotenv(get_path_mei('.env'), override=True)

def save_settings(s: dict):
    try:
        with open(get_path_mei(SETTINGS_FILE), "w", encoding="utf-8") as f:
            json.dump(s, f, indent=2)
    except Exception as e:
        print("Falha ao salvar settings:", e)

def ensure_dir(path: str):
    Path(get_path_mei(path)).mkdir(parents=True, exist_ok=True)

def get_log_file_path(now: datetime) -> str:
    return os.path.join(get_path_mei(LOGS_DIR), f"log{now.year}_{now.month}_{now.day}.txt")

def append_log(msg: str):
    ensure_dir(get_path_mei(LOGS_DIR))
    now = datetime.now(tz=tz.gettz())
    path = get_log_file_path(now)
    try:
        with open(path, "a", encoding="utf-8") as lf:
            lf.write(f"[{now.isoformat()}]\t{msg}\n")
    except Exception as e:
        print("Erro ao escrever log:", e)

def log_error(exception: Exception, message: str):
    append_log(f"[ERROR] {message} - {exception}")
    tb = traceback.format_exc()
    append_log(tb)
    try:
        app.append_log_preview(f"ERROR: {message}")
    except Exception:
        pass

def connect_db():
    db_url = os.getenv("DATABASE_URL")
    if not db_url:
        raise Exception("URL do banco de dados não configurado no ambiente")
    conn = psycopg2.connect(db_url)
    cur = conn.cursor(cursor_factory=RealDictCursor)
    return conn, cur

def load_processed() -> set:
    try:
        if os.path.exists(get_path_mei(PROCESSED_FILE)):
            with open(get_path_mei(PROCESSED_FILE), "r", encoding="utf-8") as f:
                data = json.load(f)
                return set(data.get("processed", []))
    except Exception:
        pass
    return set()

def save_processed(processed_set: set):
    try:
        with open(get_path_mei(PROCESSED_FILE), "w", encoding="utf-8") as f:
            json.dump({"processed": list(processed_set)}, f)
    except Exception as e:
        log_error(e, "Falha ao salvar processed_orders.json")

def init_offline_db():
    conn = sqlite3.connect(get_path_mei(OFFLINE_DB), check_same_thread=False)
    cur = conn.cursor()
    cur.execute("""
        CREATE TABLE IF NOT EXISTS queued_orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER,
            payload TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            attempts INTEGER DEFAULT 0,
            status TEXT DEFAULT 'pending'
        )
    """)
    conn.commit()
    return conn

OFFLINE_CONN = None

def enqueue_offline(order_id: int, payload: dict):
    global OFFLINE_CONN
    try:
        if OFFLINE_CONN is None:
            OFFLINE_CONN = init_offline_db()
        cur = OFFLINE_CONN.cursor()
        cur.execute("INSERT INTO queued_orders (order_id, payload) VALUES (?, ?)", (order_id, json.dumps(payload)))
        OFFLINE_CONN.commit()
    except Exception as e:
        log_error(e, "Falha ao enfileirar pedido offline")

def retry_offline_queue(process_function):
    """Background thread trying to reprocess queued orders"""
    global OFFLINE_CONN
    if OFFLINE_CONN is None:
        OFFLINE_CONN = init_offline_db()
    cur = OFFLINE_CONN.cursor()
    while True:
        try:
            cur.execute("SELECT id, order_id, payload, attempts FROM queued_orders WHERE status='pending' ORDER BY created_at LIMIT 10")
            rows = cur.fetchall()
            if not rows:
                time.sleep(10)
                continue
            for row in rows:
                qid, oid, payload_json, attempts = row
                payload = json.loads(payload_json)
                try:
                    ok = process_function(payload, offline_retry=True)
                    if ok:
                        cur.execute("DELETE FROM queued_orders WHERE id=?", (qid,))
                    else:
                        cur.execute("UPDATE queued_orders SET attempts=attempts+1 WHERE id=?", (qid,))
                    OFFLINE_CONN.commit()
                except Exception as e:
                    cur.execute("UPDATE queued_orders SET attempts=attempts+1 WHERE id=?", (qid,))
                    OFFLINE_CONN.commit()
        except Exception as e:
            log_error(e, "Erro no worker de retry offline")
            time.sleep(10)

def ensure_exported_column(cur, conn):
    """Try to add exported column to orders if not exists (best-effort)."""
    try:
        cur.execute("ALTER TABLE orders ADD COLUMN IF NOT EXISTS exported BOOLEAN DEFAULT FALSE")
        conn.commit()
    except Exception:
        try:
            conn.rollback()
        except Exception:
            pass

def mark_order_exported_in_db(cur, conn, order_id: int):
    try:
        cur.execute("UPDATE orders SET exported = TRUE WHERE id = %s", (order_id,))
        conn.commit()
    except Exception as e:
        try:
            conn.rollback()
        except Exception:
            pass
        log_error(e, f"Não foi possível marcar order {order_id} como exported no DB")
        return False
    return True

def fetch_orders(cur, statuses: Tuple[str, ...] = ("recebido", "em_andamento")) -> List[Dict[str, Any]]:
    cur.execute(
        """
        SELECT
            o.id AS order_id,
            o.order_number,
            o.table_number,
            o.notes,
            o.created_at,
            o.pickup_time,
            COALESCE(o.customer_name, u.full_name) AS customer_name,
            u.email,
            o.phone_number,
            o.cep,
            o.address_street,
            o.address_number,
            o.address_complement,
            o.address_neighborhood,
            o.address_city,
            o.address_state,
            COALESCE(o.exported, FALSE) as exported
        FROM orders o
        LEFT JOIN users u ON u.id = o.user_id
        WHERE o.status IN %s
        ORDER BY o.created_at ASC
        """,
        (tuple(statuses),),
    )
    return cur.fetchall()

def fetch_order_items(cur, order_id: int) -> List[Dict[str, Any]]:
    cur.execute(
        """
        SELECT
            oi.quantity,
            oi.item_price,
            oi.notes,
            mi.name,
            mg.name as group_name,
            mi.id as item_pdv,
            (oi.quantity * oi.item_price) as subtotal
        FROM order_items oi
        LEFT JOIN menu_items mi ON mi.id = oi.menu_item_id
        LEFT JOIN menu_groups mg ON mg.id = mi.group_id
        WHERE oi.order_id = %s
        """,
        (order_id,),
    )
    return cur.fetchall()

def format_order_line(order: Dict[str, Any], items: List[Dict[str, Any]], order_index: int, now: datetime) -> str:
    merged = {**DEFAULT_CUSTOMER, **{k: v for k, v in order.items() if v is not None}}
    created_at = merged.get("created_at") or now.isoformat()
    pickup_time = merged.get("pickup_time") or str(now)

    line = (
        f"PEDIDO|{merged.get('customer_name') or merged.get('full_name')}|CPF|123.456.789-10|{merged.get('phone_number')}|"
        f"{merged.get('cep')}|{merged.get('address_street')}|{merged.get('address_number')}|{merged.get('address_complement')}|"
        f"{merged.get('address_neighborhood')}|{merged.get('address_city')}|{merged.get('address_state')}|AUTO-ATENDIMENTO|Moto-boy|"
        f"{merged.get('order_id', '404')}|{merged.get('notes', '')}|{merged.get('order_number', 0)}|{order_index}|"
        f"{created_at}|{pickup_time}|CARDAPIO DIGITAL|"
    )

    for item in items:
        item_pdv = item.get("item_pdv", 1000)
        notes = item.get("notes", "")
        subtotal = item.get("subtotal", 10)
        quantity = item.get("quantity", 1)
        line += (
            f" ITEM|{item_pdv}|89350031024|{notes}|0|{subtotal}|{quantity}|UNID|99999999|88888888|cest|cfop|0|500|"
            "cst_icms|icms|reducao_icms|cst_pis|pis|cst_cofins|cofins|imp_federal|imp_estadual|imp_municipal|GRUPO|"
        )

    return line.replace("None", "!!!!")

def write_order_file(content: str, month: int, day: int, order_index: int):
    ensure_dir(get_path_mei(PEDIDOS_DIR))
    file_name = os.path.join(get_path_mei(PEDIDOS_DIR), f"pedido_{month}_{day}_{order_index}.txt")
    with open(file_name, "w", encoding="utf-8") as order_file:
        order_file.write(content + "\n")
    append_log(f"Pedido escrito: {file_name}")
    return file_name

def notify_native(title: str, message: str):
    try:
        if platform.system() == "Windows" and ToastNotifier:
            toaster = ToastNotifier()
            toaster.show_toast(title, message, threaded=True, icon_path=None, duration=6)
            return
        if plyer_notification:
            plyer_notification.notify(title=title, message=message, app_name="Portuga")
            return
    except Exception:
        pass

    try:
        def _popup():
            p = ctk.CTkToplevel()
            p.title(title)
            p.geometry("380x120")
            lbl = ctk.CTkLabel(p, text=message, wraplength=360)
            lbl.pack(padx=12, pady=12)
            btn = ctk.CTkButton(p, text="Fechar", command=p.destroy)
            btn.pack(pady=(6, 12))
        app.after(0, _popup)
    except Exception:
        print("Notification fallback:", title, message)

class WSClient(threading.Thread):
    def __init__(self, url: str, on_message_callable):
        super().__init__(daemon=True)
        self.url = url
        self.on_message_callable = on_message_callable
        self.ws = None
        self.running = False

    def run(self):
        if websocket is None:
            append_log("websocket-client não instalado; WS desativado")
            return
        self.running = True
        def _on_message(ws, message):
            try:
                data = json.loads(message)
                self.on_message_callable(data)
            except Exception as e:
                append_log(f"WS mensagem inválida: {message}")
        def _on_error(ws, err):
            append_log(f"WS erro: {err}")
            time.sleep(5)
        def _on_close(ws):
            append_log("WS fechado")
            time.sleep(5)
        def _on_open(ws):
            append_log("WS conectado")
        while self.running:
            try:
                self.ws = websocket.WebSocketApp(self.url, on_message=_on_message, on_error=_on_error, on_close=_on_close, on_open=_on_open)
                self.ws.run_forever()
            except Exception as e:
                append_log(f"WS run_forever falhou: {e}")
            time.sleep(5)

    def stop(self):
        self.running = False
        try:
            if self.ws:
                self.ws.close()
        except Exception:
            pass

class main(CTK):
    def __init__(self):
        super().__init__()
        self.settings = load_settings()
        self.theme_mode = self.settings.get("theme", DEFAULT_THEME)
        self.poll_interval = int(self.settings.get("poll_interval", DEFAULT_POLL_INTERVAL))
        self.polling = self.settings.get("auto_sync", False)
        self.ws_client = None
        self.ws_thread = None
        self.processed = load_processed()
        self.offline_retry_thread = threading.Thread(target=retry_offline_queue, args=(self._process_payload,), daemon=True)
        self.offline_retry_thread.start()
        self.running_sync = False
        self.sync_lock = threading.Lock()
        self.sync_thread = None
        self.stats = {"processed_today": 0, "total_processed": len(self.processed), "total_time": 0.0}

        ctk.set_appearance_mode(THEME_PALETTE[self.theme_mode]["appearance"])
        ctk.set_default_color_theme("blue")

        self.title("Portuga Sistema")
        self.geometry("1000x680")
        self.minsize(820, 520)
        self.resizable(True, True)

        self._is_maximized = False

        self.build_ui()
        self.apply_theme()

        self.bind_all("<F5>", lambda e: self.start_sync_background())
        self.bind_all("<F11>", lambda e: self.toggle_maximize())
        self.bind_all("<Control-r>", lambda e: self.start_sync_background())

        if self.settings.get("auto_sync"):
            self.toggle_polling(True)

        ws_url = self.settings.get("ws_url", "")
        if ws_url:
            self.start_ws(ws_url)

    def build_ui(self):
        self.main_frame = ctk.CTkFrame(self, corner_radius=0)
        self.main_frame.pack(fill="both", expand=True)

        self.main_frame.grid_rowconfigure(0, weight=1)
        self.main_frame.grid_columnconfigure(0, weight=3)
        self.main_frame.grid_columnconfigure(1, weight=2)

        self.left = ctk.CTkFrame(self.main_frame, corner_radius=8)
        self.left.grid(row=0, column=0, sticky="nsew", padx=(12, 8), pady=12)

        self.right = ctk.CTkFrame(self.main_frame, corner_radius=8)
        self.right.grid(row=0, column=1, sticky="nsew", padx=(8, 12), pady=12)

        title_bar = ctk.CTkFrame(self.left, corner_radius=6)
        title_bar.pack(fill="x", padx=8, pady=(8,6))
        title = ctk.CTkLabel(title_bar, text="Portuga Sistema", font=("Inter", 20, "bold"))
        title.pack(side="left", padx=(6,8))
        self.btn_maximize = ctk.CTkButton(title_bar, text="▢", width=36, height=28, command=self.toggle_maximize)
        self.btn_maximize.pack(side="right", padx=6)

        subtitle = ctk.CTkLabel(self.left, text="Integração PDV / Web — Sync & Logs", font=("Inter", 11))
        subtitle.pack(pady=(0, 8), padx=8, anchor="w")

        btn_frame = ctk.CTkFrame(self.left, corner_radius=8)
        btn_frame.pack(padx=8, pady=6, fill="x")

        self.btn_sync = ctk.CTkButton(btn_frame, text="Sincronizar agora", command=self.start_sync_background, height=46)
        self.btn_sync.grid(row=0, column=0, padx=(8, 8), pady=8, sticky="ew")

        self.auto_var = ctk.BooleanVar(value=self.settings.get("auto_sync", False))
        self.switch_auto = ctk.CTkSwitch(btn_frame, text="Auto Sync", variable=self.auto_var, command=lambda: self.toggle_polling(None))
        self.switch_auto.grid(row=0, column=1, padx=(8,8), pady=8)

        btn_frame.grid_columnconfigure(0, weight=1)
        btn_frame.grid_columnconfigure(1, weight=0)

        interval_frame = ctk.CTkFrame(self.left, corner_radius=6)
        interval_frame.pack(padx=8, pady=(6,8), fill="x")
        lbl_interval = ctk.CTkLabel(interval_frame, text="Intervalo (s):")
        lbl_interval.pack(side="left", padx=(8,6))
        self.interval_entry = ctk.CTkEntry(interval_frame, width=100)
        self.interval_entry.insert(0, str(self.poll_interval))
        self.interval_entry.pack(side="left", padx=(0,8))

        self.progress = ctk.CTkProgressBar(self.left)
        self.progress.pack(pady=(12, 8), padx=8, fill="x")
        self.progress.set(0.0)

        self.status_label = ctk.CTkLabel(self.left, text="", font=("Inter", 11), wraplength=720, justify="left")
        self.status_label.pack(pady=8, padx=8, anchor="w")

        history_frame = ctk.CTkFrame(self.left, corner_radius=6)
        history_frame.pack(padx=8, pady=6, fill="both", expand=True)

        h_title = ctk.CTkLabel(history_frame, text="Pedidos (últimos sincronizados / do DB)", font=("Inter", 13, "bold"))
        h_title.pack(pady=(8,6), padx=8, anchor="w")

        self.orders_list = ctk.CTkScrollableFrame(history_frame, corner_radius=6)
        self.orders_list.pack(fill="both", expand=True, padx=8, pady=(6,8))

        self.orders_items = []
        refresh_btns = ctk.CTkFrame(history_frame)
        refresh_btns.pack(fill="x", padx=8, pady=(6,8))
        self.btn_refresh_history = ctk.CTkButton(refresh_btns, text="Atualizar lista do DB", command=self.refresh_orders_list)
        self.btn_refresh_history.pack(side="left", padx=6)
        self.btn_clear_processed = ctk.CTkButton(refresh_btns, text="Limpar processed", command=self.clear_processed)
        self.btn_clear_processed.pack(side="left", padx=6)

        metrics_frame = ctk.CTkFrame(self.left, corner_radius=6)
        metrics_frame.pack(padx=8, pady=6, fill="x")
        self.metrics_label = ctk.CTkLabel(metrics_frame, text="Métricas: ---", font=("Inter", 11), anchor="w")
        self.metrics_label.pack(padx=8, pady=8, fill="x")

        title_r = ctk.CTkLabel(self.right, text="Logs & Ferramentas", font=("Inter", 16, "bold"))
        title_r.pack(pady=(8,6), padx=8, anchor="w")

        list_frame = ctk.CTkFrame(self.right, corner_radius=6)
        list_frame.pack(padx=8, pady=(6,8), fill="x")
        self.log_combo = ctk.CTkComboBox(list_frame, values=self.get_log_files(), command=self.on_log_selected)
        self.log_combo.set("Selecione arquivo de log")
        self.log_combo.pack(side="left", padx=(6,8), fill="x", expand=True)
        self.btn_reload_logs = ctk.CTkButton(list_frame, text="Recarregar", command=self.reload_logs)
        self.btn_reload_logs.pack(side="left", padx=(6,8))

        self.log_text = ctk.CTkTextbox(self.right)
        self.log_text.pack(padx=8, pady=(6,12), fill="both", expand=True)
        self.log_text.configure(state="disabled")

        export_frame = ctk.CTkFrame(self.right, corner_radius=6)
        export_frame.pack(fill="x", padx=8, pady=(6,12))
        self.btn_tail = ctk.CTkButton(export_frame, text="Mostrar últimas linhas", command=lambda: self.show_tail_of_selected(200))
        self.btn_tail.pack(side="left", padx=6)
        self.btn_export_csv = ctk.CTkButton(export_frame, text="Exportar processed CSV", command=self.export_processed_csv)
        self.btn_export_csv.pack(side="left", padx=6)

    def apply_theme(self):
        p = THEME_PALETTE[self.theme_mode]
        ctk.set_appearance_mode(p["appearance"])
        try:
            self.main_frame.configure(fg_color=p["window_bg"])
            self.left.configure(fg_color=p["window_bg"])
            self.right.configure(fg_color=p["window_bg"])
        except Exception:
            pass
        try:
            self.btn_sync.configure(fg_color=p["primary"], hover_color=p["accent"], text_color=p["button_text"])
        except Exception:
            pass
        try:
            self.progress.configure(progress_color=p["primary"])
        except Exception:
            pass
        try:
            self.status_label.configure(text_color=p["muted_text"])
            self.metrics_label.configure(text_color=p["muted_text"])
        except Exception:
            pass
        try:
            self.log_text.configure(state="normal")
            self.log_text.configure(fg_color=p["log_bg"], text_color=p["log_text"])
            self.log_text.configure(state="disabled")
        except Exception:
            pass

    def toggle_maximize(self):
        """Toggle maximized window (windowed fullscreen)."""
        try:
            if platform.system() == "Windows":
                if not self._is_maximized:
                    self.state('zoomed')
                    self._is_maximized = True
                else:
                    self.state('normal')
                    self._is_maximized = False
            else:
                if not self._is_maximized:
                    try:
                        self.attributes("-zoomed", True)
                        self._is_maximized = True
                    except Exception:
                        screen_w = self.winfo_screenwidth()
                        screen_h = self.winfo_screenheight()
                        self._prev_geometry = self.geometry()
                        self.geometry(f"{screen_w}x{screen_h}+0+0")
                        self._is_maximized = True
                else:
                    try:
                        self.attributes("-zoomed", False)
                        self._is_maximized = False
                    except Exception:
                        if hasattr(self, "_prev_geometry"):
                            self.geometry(self._prev_geometry)
                        else:
                            self.state('normal')
                        self._is_maximized = False
        except Exception as e:
            append_log(f"toggle_maximize failed: {e}")

    def get_log_files(self):
        ensure_dir(get_path_mei(LOGS_DIR))
        files = [f for f in os.listdir(get_path_mei(LOGS_DIR)) if os.path.isfile(os.path.join(get_path_mei(LOGS_DIR), f))]
        files_sorted = sorted(files, reverse=True)
        return files_sorted

    def reload_logs(self):
        files = self.get_log_files()
        try:
            self.log_combo.configure(values=files)
        except Exception:
            pass
        if files:
            try:
                self.log_combo.set(files[0])
                self.on_log_selected(files[0])
            except Exception:
                pass
        else:
            try:
                self.log_combo.set("Nenhum log")
            except Exception:
                pass
            self.log_text.configure(state="normal")
            self.log_text.delete("1.0", "end")
            self.log_text.insert("end", "Nenhum arquivo de log encontrado.")
            self.log_text.configure(state="disabled")

    def on_log_selected(self, file_name):
        if not file_name or str(file_name).startswith("Nenhum"):
            return
        path = os.path.join(get_path_mei(LOGS_DIR), file_name)
        try:
            with open(path, "r", encoding="utf-8") as f:
                content = f.read()
        except Exception as e:
            content = f"Erro ao ler log: {e}"
        self.log_text.configure(state="normal")
        self.log_text.delete("1.0", "end")
        self.log_text.insert("end", content)
        self.log_text.configure(state="disabled")

    def show_tail_of_selected(self, lines: int = 200):
        try:
            sel = self.log_combo.get()
        except Exception:
            sel = None
        if not sel or str(sel).startswith("Nenhum"):
            return
        path = os.path.join(get_path_mei(LOGS_DIR), sel)
        try:
            with open(path, "r", encoding="utf-8") as f:
                content = f.readlines()
                tail = "".join(content[-lines:])
        except Exception as e:
            tail = f"Erro ao ler log: {e}"
        self.log_text.configure(state="normal")
        self.log_text.delete("1.0", "end")
        self.log_text.insert("end", tail)
        self.log_text.configure(state="disabled")

    def append_log_preview(self, message: str):
        ts = datetime.now(tz=tz.gettz()).isoformat()
        preview = f"[{ts}] {message}\n"
        def _append():
            try:
                self.log_text.configure(state="normal")
                cur = self.log_text.get("1.0", "end")
                self.log_text.delete("1.0", "end")
                self.log_text.insert("1.0", preview + cur)
                self.log_text.configure(state="disabled")
            except Exception:
                pass
        self.after(0, _append)

    def refresh_orders_list(self):
        """Recarrega a lista de pedidos do banco e atualiza o painel de histórico."""
        inner = None
        for attr in ("_frame", "inner_frame", "frame", "_scrollable_frame"):
            inner = getattr(self.orders_list, attr, None)
            if inner is not None:
                break
        container = inner if inner is not None else self.orders_list

        for child in container.winfo_children():
            try:
                child.destroy()
            except Exception:
                pass
        self.orders_items.clear()

        try:
            conn, cur = connect_db()
            cur.execute("""
                SELECT id, order_number, created_at, status, total
                FROM orders
                ORDER BY created_at DESC
                LIMIT 30
            """)
            rows = cur.fetchall()
            for r in rows:
                oid = r["id"]
                created = r.get("created_at")
                created_str = created if isinstance(created, str) else (created.isoformat() if created else "")
                txt = f"#{r.get('order_number')} - {created_str} - {r.get('status')}"
                frame = ctk.CTkFrame(container, corner_radius=6)
                frame.pack(fill="x", padx=6, pady=4)
                lbl = ctk.CTkLabel(frame, text=txt, anchor="w")
                lbl.pack(side="left", padx=8)
                btn_reprocess = ctk.CTkButton(frame, text="Reprocessar", width=110, command=lambda o=oid: self.reprocess_order(o))
                btn_reprocess.pack(side="right", padx=8)
                self.orders_items.append((frame, lbl, btn_reprocess))
            cur.close()
            conn.close()
        except Exception as e:
            self.append_log_preview("Falha ao buscar histórico: " + str(e))

    def reprocess_order(self, order_id: int):
        t = threading.Thread(target=self._process_single_order_by_id, args=(order_id,), daemon=True)
        t.start()

    def toggle_polling(self, _event=None):
        try:
            self.poll_interval = int(self.interval_entry.get() or DEFAULT_POLL_INTERVAL)
        except Exception:
            self.poll_interval = DEFAULT_POLL_INTERVAL
        self.settings["poll_interval"] = self.poll_interval
        self.settings["auto_sync"] = bool(self.auto_var.get())
        save_settings(self.settings)
        if self.auto_var.get():
            self.polling = True
            t = threading.Thread(target=self._poll_loop, daemon=True)
            t.start()
            self.append_log_preview("Auto Sync ligado")
        else:
            self.polling = False
            self.append_log_preview("Auto Sync desligado")

    def _poll_loop(self):
        while self.polling:
            if not self.running_sync:
                self.start_sync_background(auto=True)
            time.sleep(self.poll_interval)

    def start_ws(self, ws_url: str):
        if websocket is None:
            append_log("websocket-client não instalado; WS desativado")
            return
        if self.ws_client:
            self.ws_client.stop()
        self.ws_client = WSClient(ws_url, self._on_ws_message)
        self.ws_client.start()
        self.append_log_preview("WS client iniciado")

    def _on_ws_message(self, data: dict):
        try:
            action = data.get("action")
            if action == "new_order" and data.get("order_id"):
                self.append_log_preview(f"WS new_order {data['order_id']}")
                self.start_sync_background()
            elif action == "order_payload" and data.get("order"):
                self.append_log_preview("WS order_payload recebido")
                self._process_payload(data["order"])
        except Exception as e:
            append_log(f"WS on_message error: {e}")

    def start_sync_background(self, auto: bool = False):
        if self.running_sync:
            return
        self.sync_thread = threading.Thread(target=self._sync_db, args=(auto,), daemon=True)
        self.sync_thread.start()

    def _process_payload(self, payload: dict, offline_retry: bool = False) -> bool:
        """Process a single order payload (used by offline retry). Returns True if ok."""
        global ORDER_INDEX
        try:
            now = datetime.now(tz=tz.gettz())
            items = payload.get("items", [])
            line = format_order_line(payload, items, ORDER_INDEX, now)
            file_written = write_order_file(line, now.month, now.day, ORDER_INDEX)
            ORDER_INDEX += 1
            if self.settings.get("mark_exported_in_db", True) and payload.get("order_id"):
                try:
                    conn, cur = connect_db()
                    ensure_exported_column(cur, conn)
                    mark_order_exported_in_db(cur, conn, payload["order_id"])
                    cur.close(); conn.close()
                except Exception:
                    if not offline_retry:
                        enqueue_offline(payload.get("order_id"), payload)
            notify_native("Novo Pedido", f"Pedido {payload.get('order_number')} processado")
            self.append_log_preview(f"Processed payload -> {file_written}")
            return True
        except Exception as e:
            log_error(e, "Falha ao processar payload")
            return False

    def _process_single_order_by_id(self, order_id: int):
        try:
            conn, cur = connect_db()
            cur.execute("SELECT * FROM orders WHERE id = %s", (order_id,))
            order = cur.fetchone()
            if not order:
                self.append_log_preview(f"Pedido {order_id} não encontrado")
                cur.close(); conn.close()
                return
            items = fetch_order_items(cur, order_id)
            now = datetime.now(tz=tz.gettz())
            line = format_order_line(order, items, ORDER_INDEX, now)
            fpath = write_order_file(line, now.month, now.day, ORDER_INDEX)
            ensure_exported_column(cur, conn)
            mark_order_exported_in_db(cur, conn, order_id)
            self.append_log_preview(f"Pedido {order_id} reprocessado -> {fpath}")
            cur.close(); conn.close()
        except Exception as e:
            log_error(e, f"Falha ao reprocessar pedido {order_id}")

    def _sync_db(self, auto: bool = False):
        """Main sync routine (background)."""
        global ORDER_INDEX
        if not self.sync_lock.acquire(blocking=False):
            return
        self.running_sync = True
        start_time = time.time()
        try:
            conn = cur = None
            try:
                conn, cur = connect_db()
            except Exception as e:
                log_error(e, "Falha ao conectar DB")
                self.after(0, lambda: self.return_status("Erro de conexão ao banco", False))
                return

            try:
                cur.execute("SELECT is_active, restrict_orders FROM maintenance_mode WHERE id = 1")
                mm = cur.fetchone()
                if mm and mm.get("is_active") and mm.get("restrict_orders"):
                    self.after(0, lambda: self.return_status("Sistema em manutenção (pedidos restritos)", False))
                    return
            except Exception:
                pass

            try:
                ensure_exported_column(cur, conn)
                orders = fetch_orders(cur)
            except Exception as e:
                log_error(e, "Erro ao buscar pedidos")
                self.after(0, lambda: self.return_status("Erro ao buscar pedidos", False))
                return

            new_orders = [o for o in orders if not o.get("exported", False) and o["order_id"] not in self.processed]
            total = len(new_orders)
            if total == 0:
                self.after(0, lambda: self.return_status("Tudo em ordem!\nTotal de 0 pedidos sincronizados", True))
                return

            processed_local = []
            for idx, order in enumerate(new_orders, start=1):
                try:
                    items = fetch_order_items(cur, order["order_id"])
                    now = datetime.now(tz=tz.gettz())
                    line = format_order_line(order, items, ORDER_INDEX, now)
                    file_written = write_order_file(line, now.month, now.day, ORDER_INDEX)
                    ORDER_INDEX += 1
                    if self.settings.get("mark_exported_in_db", True):
                        ok = mark_order_exported_in_db(cur, conn, order["order_id"])
                        if not ok:
                            enqueue_offline(order["order_id"], {**order, "items": items})
                    processed_local.append(order["order_id"])
                    notify_native("Novo Pedido", f"Pedido {order.get('order_number')} processado.")
                    self.append_log_preview(f"Pedido {order.get('order_number')} -> {file_written}")
                except Exception as e:
                    log_error(e, f"Erro ao processar pedido {order.get('order_id')}")
                progress_value = idx / max(total, 1)
                self.after(0, lambda v=progress_value: self.progress.set(v))

            self.processed.update(processed_local)
            save_processed(self.processed)
            elapsed = time.time() - start_time
            self.stats["processed_today"] += len(processed_local)
            self.stats["total_processed"] = len(self.processed)
            self.stats["total_time"] += elapsed
            self.after(0, lambda: self.return_status(f"Sincronizado com sucesso\nTotal de {len(processed_local)} pedidos", True))
            self.after(0, lambda: self.btn_refresh_history.invoke())
        finally:
            time.sleep(0.4)
            self.after(0, lambda: self.progress.set(0.0))
            self.running_sync = False
            try:
                self.sync_lock.release()
            except Exception:
                pass

            try:
                if cur: cur.close()
            except Exception:
                pass
            try:
                if conn: conn.close()
            except Exception:
                pass
            self.after(0, lambda: self.update_metrics())

    def return_status(self, message: str, success: bool):
        p = THEME_PALETTE[self.theme_mode]
        try:
            self.status_label.configure(text=message)
            color = p["status_success"] if success else p["status_error"]
            self.status_label.configure(text_color=color)
        except Exception:
            pass
        append_log(message)
        self.append_log_preview(message)

    def update_metrics(self):
        avg = (self.stats["total_time"] / max(1, self.stats["total_processed"])) if self.stats["total_processed"] > 0 else 0.0
        s = f"Hoje: {self.stats['processed_today']} pedidos | Total proces.: {self.stats['total_processed']} | Tempo médio: {avg:.2f}s"
        try:
            self.metrics_label.configure(text=s)
        except Exception:
            pass

    def clear_processed(self):
        self.processed = set()
        save_processed(self.processed)
        self.append_log_preview("Arquivo processed_orders limpo")
        self.update_metrics()

    def export_processed_csv(self):
        try:
            fpath = "processed_export.csv"
            with open(fpath, "w", encoding="utf-8") as f:
                f.write("order_id\n")
                for oid in sorted(self.processed):
                    f.write(f"{oid}\n")
            self.append_log_preview(f"Exportado processed -> {fpath}")
        except Exception as e:
            log_error(e, "Erro ao exportar CSV")

if __name__ == "__main__":
    ensure_dir(get_path_mei(LOGS_DIR))
    ensure_dir(get_path_mei(PEDIDOS_DIR))
    OFFLINE_CONN = init_offline_db()
    app = main()
    app.reload_logs()
    app.mainloop()
