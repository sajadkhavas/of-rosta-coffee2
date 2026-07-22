import { createFileRoute } from "@tanstack/react-router";
import { StructuredContentPage } from "@/components/content/StructuredContentPage";
import { contentPathQueryOptions } from "@/lib/api/content";
import { contentSeoHead } from "@/lib/seo";

export const Route = createFileRoute("/compare/$slug")({
  loader: ({ params, context }) =>
    context.queryClient.ensureQueryData(
      contentPathQueryOptions(`/compare/${params.slug}`),
    ),
  head: ({ loaderData }) => contentSeoHead(loaderData),
  component: ComparisonContentPage,
});

function ComparisonContentPage() {
  return <StructuredContentPage entry={Route.useLoaderData()} />;
}
