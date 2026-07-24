import { createFileRoute } from "@tanstack/react-router";
import { StructuredContentPage } from "@/components/content/StructuredContentPage";
import { contentPathQueryOptions } from "@/lib/api/content";
import { contentSeoHead } from "@/lib/seo";

export const Route = createFileRoute("/collections/$slug")({
  loader: ({ params, context }) =>
    context.queryClient.ensureQueryData(contentPathQueryOptions(`/collections/${params.slug}`)),
  head: ({ loaderData }) => (loaderData ? contentSeoHead(loaderData) : {}),
  component: CollectionContentPage,
});

function CollectionContentPage() {
  return <StructuredContentPage entry={Route.useLoaderData()} />;
}
