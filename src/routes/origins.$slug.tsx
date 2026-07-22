import { createFileRoute } from "@tanstack/react-router";
import { StructuredContentPage } from "@/components/content/StructuredContentPage";
import { contentPathQueryOptions } from "@/lib/api/content";
import { contentSeoHead } from "@/lib/seo";

export const Route = createFileRoute("/origins/$slug")({
  loader: ({ params, context }) =>
    context.queryClient.ensureQueryData(
      contentPathQueryOptions(`/origins/${params.slug}`),
    ),
  head: ({ loaderData }) => contentSeoHead(loaderData),
  component: OriginContentPage,
});

function OriginContentPage() {
  return <StructuredContentPage entry={Route.useLoaderData()} />;
}
