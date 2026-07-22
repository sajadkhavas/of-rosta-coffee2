import { createFileRoute } from "@tanstack/react-router";
import { StructuredContentPage } from "@/components/content/StructuredContentPage";
import { contentPathQueryOptions } from "@/lib/api/content";
import { contentSeoHead } from "@/lib/seo";

export const Route = createFileRoute("/brew/$slug")({
  loader: ({ params, context }) =>
    context.queryClient.ensureQueryData(
      contentPathQueryOptions(`/brew/${params.slug}`),
    ),
  head: ({ loaderData }) => contentSeoHead(loaderData),
  component: BrewContentPage,
});

function BrewContentPage() {
  return <StructuredContentPage entry={Route.useLoaderData()} />;
}
