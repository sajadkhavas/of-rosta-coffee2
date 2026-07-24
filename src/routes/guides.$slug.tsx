import { createFileRoute } from "@tanstack/react-router";
import { StructuredContentPage } from "@/components/content/StructuredContentPage";
import { contentPathQueryOptions } from "@/lib/api/content";
import { contentSeoHead } from "@/lib/seo";

export const Route = createFileRoute("/guides/$slug")({
  loader: ({ params, context }) =>
    context.queryClient.ensureQueryData(
      contentPathQueryOptions(`/guides/${params.slug}`),
    ),
  head: ({ loaderData }) => (loaderData ? contentSeoHead(loaderData) : {}),
  component: GuidePage,
});

function GuidePage() {
  return <StructuredContentPage entry={Route.useLoaderData()} />;
}
