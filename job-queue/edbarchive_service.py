#!/usr/bin/env python3

import asyncio
import hashlib
import json
import os
import signal
import tempfile
import traceback
from datetime import datetime
from pathlib import Path
from typing import Any, Optional

import mariadb
from dotenv import load_dotenv

load_dotenv()

# ============================================================
# Configuration
# ============================================================

TCP_HOST = os.getenv("EDB_WORKER_HOST", "127.0.0.1")
TCP_PORT = int(os.getenv("EDB_WORKER_PORT", "9100"))

POLL_INTERVAL_SECONDS = int(os.getenv("EDB_WORKER_POLL_INTERVAL", "60"))

DB_HOST = os.getenv("EDB_DB_HOST", "127.0.0.1")
DB_PORT = int(os.getenv("EDB_DB_PORT", "3306"))
DB_NAME = os.getenv("EDB_DB_NAME", "EDBArchive")
DB_USER = os.getenv("EDB_DB_USER", "root")
DB_PASSWORD = os.getenv("EDB_DB_PASSWORD", "")

BASE_DIR = Path(__file__).resolve().parent

def resolve_path_from_worker(path_value: str) -> Path:
    path = Path(path_value)

    if path.is_absolute():
        return path

    return (BASE_DIR / path).resolve()

# EDB_WORKSPACE_DIR points to Laravel's storage/app directory.
WORKSPACE_DIR = resolve_path_from_worker(
    os.getenv("EDB_WORKSPACE_DIR", "../storage")
)
JOB_BASE_DIR = WORKSPACE_DIR / "fhe-jobs"

FHE_WRAPPER = resolve_path_from_worker(
    os.getenv("EDB_FHE_WRAPPER", "../backend/fhe_wrapper.sh")
)

RUNTIME_DIR = resolve_path_from_worker(
    os.getenv(
        "EDB_RUNTIME_DIR",
        str(Path(tempfile.gettempdir()) / "edbarchive"),
    )
)
DB_CONFIG_PATH = RUNTIME_DIR / "db.json"

wake_event = asyncio.Event()
shutdown_event = asyncio.Event()


# Add a local-time ISO 8601 timestamp to service logs for audit/debugging.
def log(message: str) -> None:
    timestamp = datetime.now().astimezone().isoformat(timespec="seconds")
    print(f"[{timestamp}] {message}", flush=True)


# ============================================================
# Database helpers
# ============================================================

def db_connect() -> mariadb.Connection:
    return mariadb.connect(
        host=DB_HOST,
        port=DB_PORT,
        user=DB_USER,
        password=DB_PASSWORD,
        database=DB_NAME,
        autocommit=False,
    )


def claim_next_pending_job() -> Optional[dict[str, Any]]:
    """
    Single-worker claim logic.

    This marks the oldest pending job as running, then returns it.
    The UPDATE condition protects against accidental double-claiming.
    """
    conn = db_connect()

    try:
        cur = conn.cursor(dictionary=True)

        cur.execute(
            """
            SELECT id
            FROM fhe_jobs
            WHERE status = 'pending'
            ORDER BY created_at ASC, id ASC
            LIMIT 1
            FOR UPDATE
            """
        )

        row = cur.fetchone()

        if row is None:
            conn.commit()
            return None

        job_id = row["id"]

        cur.execute(
            """
            UPDATE fhe_jobs
            SET
                status = 'running',
                progress = 0,
                claimed_at = NOW(),
                started_at = NOW(),
                updated_at = NOW(),
                exit_code = NULL,
                error_type = NULL,
                error_code = NULL,
                error_message = NULL,
                stdout_log = NULL,
                stderr_log = NULL,
                result_payload = NULL
            WHERE id = ?
              AND status = 'pending'
            """,
            (job_id,),
        )

        if cur.rowcount != 1:
            conn.rollback()
            return None

        cur.execute(
            """
            SELECT *
            FROM fhe_jobs
            WHERE id = ?
            """,
            (job_id,),
        )

        job = cur.fetchone()

        # Keep the domain record status aligned with the claimed FHE job.
        if job.get("pk") is not None:
            target_id = int(job["pk"])
            job_type = str(job["job_type"])

            if job_type == "create-context":
                cur.execute(
                    """
                    UPDATE fhe_contexts
                    SET context_status = 'processing', updated_at = NOW()
                    WHERE id = ?
                    """,
                    (target_id,),
                )
            elif job_type == "create-keypair":
                cur.execute(
                    """
                    UPDATE fhe_key_registry
                    SET generation_status = 'processing', updated_at = NOW()
                    WHERE id = ?
                    """,
                    (target_id,),
                )
            elif job_type == "backup":
                cur.execute(
                    """
                    UPDATE shares
                    SET bundle_status = 'processing', updated_at = NOW()
                    WHERE id = ?
                    """,
                    (target_id,),
                )

        conn.commit()
        return job

    except Exception:
        conn.rollback()
        raise

    finally:
        conn.close()


def update_job_progress(job_id: int, progress: int) -> None:
    progress = max(0, min(100, progress))

    conn = db_connect()

    try:
        cur = conn.cursor()

        cur.execute(
            """
            UPDATE fhe_jobs
            SET progress = ?, updated_at = NOW()
            WHERE id = ?
            """,
            (progress, job_id),
        )

        conn.commit()

    except Exception:
        conn.rollback()
        raise

    finally:
        conn.close()


def mark_job_completed(
    job: dict[str, Any],
    result_payload: dict[str, Any],
    stdout_log: str = "",
    stderr_log: str = "",
    exit_code: int = 0,
) -> None:
    # Resolve the target record and expected artifact from the job type.
    job_id = int(job["id"])
    job_type = str(job["job_type"])
    target_id_value = job.get("pk")

    if target_id_value is None:
        raise RuntimeError(f"Job {job_id} does not have a target record ID.")

    target_id = int(target_id_value)

    if target_id <= 0:
        raise RuntimeError(f"Job {job_id} has an invalid target record ID.")

    if job_type == "create-context":
        artifact_ref = f"private/{job_id}/context.bin"
        artifact_path = WORKSPACE_DIR / artifact_ref

        if not artifact_path.is_file():
            raise RuntimeError(f"Context artifact does not exist: {artifact_path}")

        target_select_sql = "SELECT id FROM fhe_contexts WHERE id = ? FOR UPDATE"
        target_update_sql = """
            UPDATE fhe_contexts
            SET
                context_file_ref = ?,
                context_status = 'generated',
                updated_at = NOW()
            WHERE id = ?
        """
        target_update_values = (artifact_ref, target_id)

    elif job_type == "create-keypair":
        artifact_ref = f"private/{job_id}"
        artifact_dir = WORKSPACE_DIR / artifact_ref
        private_key_path = artifact_dir / "private.bin"
        public_key_path = artifact_dir / "public.bin"

        if not private_key_path.is_file() or not public_key_path.is_file():
            raise RuntimeError(f"Keypair artifacts are incomplete: {artifact_dir}")

        target_select_sql = "SELECT id FROM fhe_key_registry WHERE id = ? FOR UPDATE"
        target_update_sql = """
            UPDATE fhe_key_registry
            SET
                keyset_ref = ?,
                generation_status = 'generated',
                updated_at = NOW()
            WHERE id = ?
        """
        target_update_values = (artifact_ref, target_id)

    elif job_type == "backup":
        artifact_ref = f"public/{job_id}/bundle.tar.gz"
        artifact_path = WORKSPACE_DIR / artifact_ref

        if not artifact_path.is_file():
            raise RuntimeError(f"Backup artifact does not exist: {artifact_path}")

        # Hash the final bundle bytes before publishing its reference.
        bundle_hasher = hashlib.sha256()

        with artifact_path.open("rb") as bundle_file:
            for chunk in iter(lambda: bundle_file.read(1024 * 1024), b""):
                bundle_hasher.update(chunk)

        bundle_hash = bundle_hasher.hexdigest()
        target_select_sql = "SELECT id FROM shares WHERE id = ? FOR UPDATE"
        target_update_sql = """
            UPDATE shares
            SET
                bundle_ref = ?,
                bundle_hash = ?,
                bundle_status = 'done',
                updated_at = NOW()
            WHERE id = ?
        """
        target_update_values = (artifact_ref, bundle_hash, target_id)

    else:
        raise RuntimeError(f"Unsupported completed job type: {job_type}")

    conn = db_connect()

    try:
        cur = conn.cursor()

        # Lock and update the domain record in the same transaction as fhe_jobs.
        cur.execute(target_select_sql, (target_id,))

        if cur.fetchone() is None:
            raise RuntimeError(
                f"Target record {target_id} does not exist for job {job_id}."
            )

        cur.execute(target_update_sql, target_update_values)

        cur.execute(
            """
            UPDATE fhe_jobs
            SET
                status = 'completed',
                progress = 100,
                exit_code = ?,
                result_payload = ?,
                stdout_log = ?,
                stderr_log = ?,
                error_type = NULL,
                error_code = NULL,
                error_message = NULL,
                finished_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
              AND status = 'running'
            """,
            (
                exit_code,
                json.dumps(result_payload, ensure_ascii=False),
                stdout_log,
                stderr_log,
                job_id,
            ),
        )

        if cur.rowcount != 1:
            raise RuntimeError(
                f"Job {job_id} was not in running state when completing it."
            )

        conn.commit()

    except Exception:
        conn.rollback()
        raise

    finally:
        conn.close()


def mark_job_failed(
    job: dict[str, Any],
    *,
    exit_code: Optional[int],
    error_type: str,
    error_code: str,
    error_message: str,
    result_payload: Optional[dict[str, Any]] = None,
    stdout_log: str = "",
    stderr_log: str = "",
) -> None:
    job_id = int(job["id"])

    if result_payload is None:
        result_payload = {
            "ok": False,
            "status": "failed",
            "error": {
                "type": error_type,
                "code": error_code,
                "message": error_message,
            },
        }

    conn = db_connect()

    try:
        cur = conn.cursor()

        # Publish the failure state to the domain record with the job failure.
        if job.get("pk") is not None:
            target_id = int(job["pk"])
            job_type = str(job["job_type"])

            if job_type == "create-context":
                cur.execute(
                    """
                    UPDATE fhe_contexts
                    SET context_status = 'failed', updated_at = NOW()
                    WHERE id = ?
                    """,
                    (target_id,),
                )
            elif job_type == "create-keypair":
                cur.execute(
                    """
                    UPDATE fhe_key_registry
                    SET generation_status = 'failed', updated_at = NOW()
                    WHERE id = ?
                    """,
                    (target_id,),
                )
            elif job_type == "backup":
                cur.execute(
                    """
                    UPDATE shares
                    SET bundle_status = 'error', updated_at = NOW()
                    WHERE id = ?
                    """,
                    (target_id,),
                )

        cur.execute(
            """
            UPDATE fhe_jobs
            SET
                status = 'failed',
                exit_code = ?,
                error_type = ?,
                error_code = ?,
                error_message = ?,
                result_payload = ?,
                stdout_log = ?,
                stderr_log = ?,
                finished_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
            """,
            (
                exit_code,
                error_type,
                error_code,
                error_message,
                json.dumps(result_payload, ensure_ascii=False),
                stdout_log,
                stderr_log,
                job_id,
            ),
        )

        conn.commit()

    except Exception:
        conn.rollback()
        raise

    finally:
        conn.close()


# ============================================================
# Job config / backend execution
# ============================================================
async def run_backend(
    job: dict[str, Any],
    result_path: Path,
) -> dict[str, Any]:
    # The configured executable is the wrapper, which locates config.json from
    # the workspace and job ID and writes result.json before it exits.
    if not FHE_WRAPPER.is_file():
        raise RuntimeError(f"FHE wrapper does not exist: {FHE_WRAPPER}")

    if not os.access(FHE_WRAPPER, os.X_OK):
        raise RuntimeError(f"FHE wrapper is not executable: {FHE_WRAPPER}")

    process = await asyncio.create_subprocess_exec(
        str(FHE_WRAPPER),
        "--workspace",
        str(WORKSPACE_DIR),
        "--id",
        str(job["id"]),
        "--db",
        str(DB_CONFIG_PATH),
        stdout=asyncio.subprocess.PIPE,
        stderr=asyncio.subprocess.PIPE,
        start_new_session=True,
    )

    # Stop the whole wrapper process group when the worker is cancelled.
    try:
        stdout_bytes, stderr_bytes = await process.communicate()
    except asyncio.CancelledError:
        if process.returncode is None:
            try:
                os.killpg(process.pid, signal.SIGTERM)
            except ProcessLookupError:
                pass

        try:
            await asyncio.wait_for(process.wait(), timeout=5)
        except asyncio.TimeoutError:
            try:
                os.killpg(process.pid, signal.SIGKILL)
            except ProcessLookupError:
                pass
            await process.wait()

        raise

    stdout_text = stdout_bytes.decode("utf-8", errors="replace")
    stderr_text = stderr_bytes.decode("utf-8", errors="replace")

    # Treat a missing, malformed, or inconsistent result as a worker error.
    result_from_wrapper = False

    if not result_path.is_file():
        result_payload = {
            "ok": False,
            "status": "failed",
            "error": {
                "type": "internal_error",
                "code": "NO_RESULT_FILE",
                "message": "The FHE wrapper exited without producing result.json.",
            },
        }
    else:
        try:
            result_payload = json.loads(result_path.read_text(encoding="utf-8"))
            result_from_wrapper = isinstance(result_payload, dict)
        except (OSError, json.JSONDecodeError) as exc:
            result_payload = {
                "ok": False,
                "status": "failed",
                "error": {
                    "type": "internal_error",
                    "code": "INVALID_RESULT_JSON",
                    "message": f"Could not read a valid result.json: {exc}",
                },
            }

    if not isinstance(result_payload, dict):
        result_payload = {
            "ok": False,
            "status": "failed",
            "error": {
                "type": "internal_error",
                "code": "INVALID_RESULT_PAYLOAD",
                "message": "result.json must contain a JSON object.",
            },
        }

    if result_from_wrapper:
        result_exit_code = result_payload.get("exit_code")
        result_ok = result_payload.get("ok")
        result_status = result_payload.get("status")

        if (
            not isinstance(result_exit_code, int)
            or isinstance(result_exit_code, bool)
            or not isinstance(result_ok, bool)
            or result_status not in {"completed", "failed"}
        ):
            result_payload = {
                "ok": False,
                "status": "failed",
                "error": {
                    "type": "internal_error",
                    "code": "INVALID_RESULT_SCHEMA",
                    "message": "result.json does not contain the required result fields.",
                },
            }
        elif (
            result_exit_code != process.returncode
            or result_ok != (process.returncode == 0)
            or result_status != ("completed" if result_ok else "failed")
        ):
            result_payload = {
                "ok": False,
                "status": "failed",
                "error": {
                    "type": "internal_error",
                    "code": "RESULT_EXIT_CODE_MISMATCH",
                    "message": (
                        "The process exit code does not match the exit code in "
                        "result.json."
                    ),
                },
            }

    return {
        "exit_code": process.returncode,
        "stdout": stdout_text,
        "stderr": stderr_text,
        "result": result_payload,
    }


async def process_job(job: dict[str, Any]) -> None:
    job_id = int(job["id"])
    job_type = str(job["job_type"])
    stdout_log = ""
    stderr_log = ""

    try:
        update_job_progress(job_id, 5)

        payload_text = job.get("input_payload")

        if payload_text is None or payload_text.strip() == "":
            input_payload = {}
        else:
            try:
                input_payload = json.loads(payload_text)
            except json.JSONDecodeError as exc:
                raise ValueError(f"Invalid input_payload JSON: {exc}") from exc

            if not isinstance(input_payload, dict):
                raise ValueError("input_payload must be a JSON object.")

        job_dir = JOB_BASE_DIR / str(job_id)
        job_dir.mkdir(parents=True, exist_ok=True)

        config_path = job_dir / "config.json"
        result_path = job_dir / "result.json"

        execution_config = {
            "job_id": job["id"],
            "job_type": job["job_type"],
            "pk": job.get("pk"),
            "input": input_payload,
        }

        config_path.write_text(
            json.dumps(execution_config, indent=2, ensure_ascii=False),
            encoding="utf-8",
        )

        update_job_progress(job_id, 10)

        backend_result = await run_backend(job, result_path)

        exit_code = backend_result["exit_code"]
        stdout_log = backend_result.get("stdout", "")
        stderr_log = backend_result.get("stderr", "")
        result_payload = backend_result.get("result") or {}

        if exit_code == 0 and result_payload.get("ok", False) is True:
            mark_job_completed(
                job,
                result_payload=result_payload,
                stdout_log=stdout_log,
                stderr_log=stderr_log,
                exit_code=exit_code,
            )
        else:
            error = result_payload.get("error", {})
            mark_job_failed(
                job,
                exit_code=exit_code,
                error_type=error.get("type", "backend_error"),
                error_code=error.get("code", "BACKEND_FAILED"),
                error_message=error.get("message", f"FHE backend failed for job_type={job_type}."),
                result_payload=result_payload,
                stdout_log=stdout_log,
                stderr_log=stderr_log,
            )

    except asyncio.CancelledError:
        # A shutdown must not leave a claimed job permanently in running state.
        mark_job_failed(
            job,
            exit_code=143,
            error_type="worker_error",
            error_code="WORKER_CANCELLED",
            error_message="The worker stopped while the job was running.",
            stdout_log=stdout_log,
            stderr_log=stderr_log,
        )
        raise

    except ValueError as exc:
        mark_job_failed(
            job,
            exit_code=2,
            error_type="validation_error",
            error_code="INVALID_JOB_INPUT",
            error_message=str(exc),
            stdout_log=stdout_log,
            stderr_log=stderr_log + traceback.format_exc(),
        )

    except Exception as exc:
        mark_job_failed(
            job,
            exit_code=9,
            error_type="internal_error",
            error_code="WORKER_EXCEPTION",
            error_message=str(exc),
            stdout_log=stdout_log,
            stderr_log=stderr_log + traceback.format_exc(),
        )


# ============================================================
# Worker loop
# ============================================================

async def worker_loop() -> None:
    log("[worker] started")

    while not shutdown_event.is_set():
        try:
            await asyncio.wait_for(
                wake_event.wait(),
                timeout=POLL_INTERVAL_SECONDS,
            )
        except asyncio.TimeoutError:
            pass

        wake_event.clear()

        while not shutdown_event.is_set():
            try:
                job = claim_next_pending_job()
            except Exception as exc:
                log(f"[worker] database claim failed: {exc}")
                await asyncio.sleep(5)
                break

            if job is None:
                break

            log(f"[worker] processing job id={job['id']} type={job['job_type']}")
            await process_job(job)

    log("[worker] stopped")


# ============================================================
# TCP server
# ============================================================

async def handle_client(
    reader: asyncio.StreamReader,
    writer: asyncio.StreamWriter,
) -> None:
    try:
        data = await asyncio.wait_for(reader.readline(), timeout=2)
        command = data.decode("utf-8", errors="replace").strip().upper()

        if command == "WAKE":
            wake_event.set()
            writer.write(b"OK\n")

        elif command == "HEALTH":
            writer.write(b"OK RUNNING\n")

        elif command == "STOP":
            shutdown_event.set()
            wake_event.set()
            writer.write(b"OK STOPPING\n")

        else:
            writer.write(b"ERR UNKNOWN_COMMAND\n")

        await writer.drain()

    except Exception:
        writer.write(b"ERR\n")
        await writer.drain()

    finally:
        writer.close()
        await writer.wait_closed()


def request_shutdown() -> None:
    shutdown_event.set()
    wake_event.set()


async def main() -> None:
    JOB_BASE_DIR.mkdir(parents=True, exist_ok=True)
    RUNTIME_DIR.mkdir(parents=True, exist_ok=True, mode=0o700)

    db_config_fd = os.open(
        DB_CONFIG_PATH,
        os.O_WRONLY | os.O_CREAT | os.O_TRUNC,
        0o600,
    )
    os.fchmod(db_config_fd, 0o600)

    with os.fdopen(db_config_fd, "w", encoding="utf-8") as db_config_file:
        json.dump(
            {
                "host": DB_HOST,
                "port": DB_PORT,
                "user": DB_USER,
                "pass": DB_PASSWORD,
            },
            db_config_file,
            indent=2,
        )
        db_config_file.write("\n")

    log(f"[config] workspace dir: {WORKSPACE_DIR}")
    log(f"[config] job base dir: {JOB_BASE_DIR}")
    log(f"[config] DB config: {DB_CONFIG_PATH}")
    log(f"[config] FHE wrapper: {FHE_WRAPPER}")

    loop = asyncio.get_running_loop()

    try:
        loop.add_signal_handler(signal.SIGINT, request_shutdown)
        loop.add_signal_handler(signal.SIGTERM, request_shutdown)
    except NotImplementedError:
        # Needed for some platforms, especially Windows.
        pass

    server = await asyncio.start_server(handle_client, TCP_HOST, TCP_PORT)

    log(f"[tcp] listening on {TCP_HOST}:{TCP_PORT}")

    server_task = asyncio.create_task(server.serve_forever())
    worker_task = asyncio.create_task(worker_loop())

    try:
        await shutdown_event.wait()

    except KeyboardInterrupt:
        request_shutdown()

    finally:
        log("[main] shutting down")

        server.close()
        await server.wait_closed()

        server_task.cancel()
        worker_task.cancel()

        await asyncio.gather(
            server_task,
            worker_task,
            return_exceptions=True,
        )

        log("[main] stopped")


if __name__ == "__main__":
    asyncio.run(main())
