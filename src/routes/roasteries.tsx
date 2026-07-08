import { createFileRoute, Outlet } from "@tanstack/react-router";

export const Route = createFileRoute("/roasteries")({
  component: () => <Outlet />,
});
