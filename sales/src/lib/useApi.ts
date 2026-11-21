import { useCallback, useEffect, useRef, useState } from "react";
import { api, ApiError } from "./api";

/** GET a path; refetches when the path changes and on reload(). Ignores stale responses. */
export function useApi<T>(path: string | null) {
  const [data, setData] = useState<T | null>(null);
  const [error, setError] = useState<ApiError | null>(null);
  const [loading, setLoading] = useState(path !== null);
  const [tick, setTick] = useState(0);
  const latest = useRef(0);

  useEffect(() => {
    if (path === null) return;
    const request = ++latest.current;
    const controller = new AbortController();
    setLoading(true);
    api<T>(path, { signal: controller.signal })
      .then((body) => { if (request === latest.current) { setData(body); setError(null); } })
      .catch((err) => { if (request === latest.current && (err as Error).name !== "AbortError") setError(err instanceof ApiError ? err : new ApiError(0, String(err))); })
      .finally(() => { if (request === latest.current) setLoading(false); });
    return () => controller.abort();
  }, [path, tick]);

  const reload = useCallback(() => setTick((value) => value + 1), []);
  return { data, error, loading, reload, setData };
}
