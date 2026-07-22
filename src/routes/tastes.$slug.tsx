import { createFileRoute } from "@tanstack/react-router";
import { StructuredContentPage } from "@/components/content/StructuredContentPage";
import { contentPathQueryOptions } from "@/lib/api/content";
import { contentSeoHead } from "@/lib/seo";

export const Route = createFileRoute("/tastes/$slug")({
  loader: ({ params, context }) =>
    context.queryClient.ensureQueryData(
      contentPathQueryOptions(`/tastes/${params.slug}`),
    ),
  head: ({ loaderData }) => contentSeoHead(loaderData),
  component: TasteContentPage,
});

function TasteContentPage() {
  return <StructuredContentPage entry={Route.useLoaderData()} />;
}
