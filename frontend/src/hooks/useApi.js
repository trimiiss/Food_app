import { useCallback, useEffect, useState } from 'react'

/**
 * Minimal data-fetching hook: runs `fetcher({ signal })` whenever `deps` change.
 *
 * - Aborts the in-flight request on unmount or when deps change, so a slow
 *   response for an old filter can never overwrite a newer one.
 * - Keeps the previous data while reloading (no layout flicker) and when a
 *   reload fails, so one failed background poll doesn't blank the page.
 *
 * A library like TanStack Query would add caching and retries; for a handful
 * of screens this ~30-line hook keeps the dependency list short.
 */
export function useApi(fetcher, deps = []) {
  const [state, setState] = useState({ data: null, error: null, loading: true })
  const [reloadToken, setReloadToken] = useState(0)

  useEffect(() => {
    const controller = new AbortController()
    setState((previous) => ({ ...previous, loading: true, error: null }))

    fetcher({ signal: controller.signal })
      .then((data) => {
        if (!controller.signal.aborted) setState({ data, error: null, loading: false })
      })
      .catch((error) => {
        if (!controller.signal.aborted) setState((previous) => ({ data: previous.data, error, loading: false }))
      })

    return () => controller.abort()
    // `fetcher` is usually an inline arrow; callers list its real inputs in `deps`.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [...deps, reloadToken])

  const reload = useCallback(() => setReloadToken((token) => token + 1), [])

  // Replace data locally, e.g. with the updated order returned by a mutation.
  const setData = useCallback(
    (updater) =>
      setState((previous) => ({
        ...previous,
        data: typeof updater === 'function' ? updater(previous.data) : updater,
      })),
    [],
  )

  return { ...state, reload, setData }
}
