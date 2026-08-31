import {
  QueryClient,
  dehydrate,
  hydrate,
  type DehydratedState,
} from "@tanstack/react-query";
import { createRouter } from "@tanstack/react-router";
import { routeTree } from "./routeTree.gen";

function shouldRetryQuery(failureCount: number, error: unknown): boolean {
  if (failureCount >= 2) return false;
  if (!error || typeof error !== "object") return true;

  const candidate = error as { status?: unknown; kind?: unknown };
  if (
    candidate.kind === "aborted" ||
    candidate.kind === "configuration" ||
    candidate.kind === "timeout"
  ) {
    return candidate.kind === "timeout" && failureCount < 1;
  }

  if (typeof candidate.status !== "number" || candidate.status === 0) {
    return true;
  }

  return candidate.status === 408 || candidate.status === 429 || candidate.status >= 500;
}

export const getRouter = () => {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        staleTime: 30_000,
        gcTime: 10 * 60_000,
        refetchOnReconnect: true,
        refetchOnWindowFocus: false,
        retry: shouldRetryQuery,
      },
      mutations: {
        retry: false,
      },
    },
  });

  const router = createRouter({
    routeTree,
    context: { queryClient },
    scrollRestoration: true,
    defaultPreloadStaleTime: 30_000,
    dehydrate: () => ({
      queryClientState: JSON.stringify(dehydrate(queryClient)),
    }),
    hydrate: (dehydrated) => {
      const queryClientState = JSON.parse(dehydrated.queryClientState) as DehydratedState;
      hydrate(queryClient, queryClientState);
    },
  });

  return router;
};
