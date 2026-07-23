/* eslint-disable */
// @ts-nocheck
// Temporary release-baseline route tree. Replace with generated routeTree.gen.ts
// after the real TanStack generator succeeds in the server toolchain.

import { Route as rootRouteImport } from "./routes/__root";
import { Route as AboutRouteImport } from "./routes/about";
import { Route as AdminContentEditEntryIdRouteImport } from "./routes/admin.content-edit.$entryId";
import { Route as AdminContentLinksRouteImport } from "./routes/admin.content-links";
import { Route as AdminContentRouteImport } from "./routes/admin.content";
import { Route as AdminFinanceRouteImport } from "./routes/admin.finance";
import { Route as AdminOperationsRouteImport } from "./routes/admin.operations";
import { Route as AuthRouteImport } from "./routes/auth";
import { Route as AuthIndexRouteImport } from "./routes/auth.index";
import { Route as AuthVerifyRouteImport } from "./routes/auth.verify";
import { Route as BlogRouteImport } from "./routes/blog";
import { Route as BlogIndexRouteImport } from "./routes/blog.index";
import { Route as BlogSlugRouteImport } from "./routes/blog.$slug";
import { Route as BrewSlugRouteImport } from "./routes/brew.$slug";
import { Route as CartRouteImport } from "./routes/cart";
import { Route as CheckoutRouteImport } from "./routes/checkout";
import { Route as CollectionsSlugRouteImport } from "./routes/collections.$slug";
import { Route as CompareSlugRouteImport } from "./routes/compare.$slug";
import { Route as ContactRouteImport } from "./routes/contact";
import { Route as DesignSystemRouteImport } from "./routes/design-system";
import { Route as ForbiddenRouteImport } from "./routes/forbidden";
import { Route as GuidesSlugRouteImport } from "./routes/guides.$slug";
import { Route as IndexRouteImport } from "./routes/index";
import { Route as OrdersRouteImport } from "./routes/orders";
import { Route as OrdersIdRouteImport } from "./routes/orders.$id";
import { Route as OrdersIndexRouteImport } from "./routes/orders.index";
import { Route as OriginsSlugRouteImport } from "./routes/origins.$slug";
import { Route as PanelManageRouteImport } from "./routes/panel.manage";
import { Route as PanelRouteImport } from "./routes/panel";
import { Route as PrivacyRouteImport } from "./routes/privacy";
import { Route as ProductsRouteImport } from "./routes/products";
import { Route as ProductsSlugRouteImport } from "./routes/products.$slug";
import { Route as ProductsIndexRouteImport } from "./routes/products.index";
import { Route as ProfileRouteImport } from "./routes/profile";
import { Route as QuizRouteImport } from "./routes/quiz";
import { Route as RoasteriesRouteImport } from "./routes/roasteries";
import { Route as RoasteriesSlugRouteImport } from "./routes/roasteries.$slug";
import { Route as RoasteriesIndexRouteImport } from "./routes/roasteries.index";
import { Route as RobotsDottxtRouteImport } from "./routes/robots[.]txt";
import { Route as SearchRouteImport } from "./routes/search";
import { Route as SitemapDotxmlRouteImport } from "./routes/sitemap[.]xml";
import { Route as TastesSlugRouteImport } from "./routes/tastes.$slug";
import { Route as TermsRouteImport } from "./routes/terms";

const rootChild = (route: any, id: string) =>
  route.update({
    id,
    path: id,
    getParentRoute: () => rootRouteImport,
  } as any);

const IndexRoute = IndexRouteImport.update({
  id: "/",
  path: "/",
  getParentRoute: () => rootRouteImport,
} as any);
const AboutRoute = rootChild(AboutRouteImport, "/about");
const AdminContentEditEntryIdRoute = rootChild(
  AdminContentEditEntryIdRouteImport,
  "/admin/content-edit/$entryId",
);
const AdminContentLinksRoute = rootChild(
  AdminContentLinksRouteImport,
  "/admin/content-links",
);
const AdminContentRoute = rootChild(AdminContentRouteImport, "/admin/content");
const AdminFinanceRoute = rootChild(AdminFinanceRouteImport, "/admin/finance");
const AdminOperationsRoute = rootChild(AdminOperationsRouteImport, "/admin/operations");
const AuthRoute = rootChild(AuthRouteImport, "/auth");
const BlogRoute = rootChild(BlogRouteImport, "/blog");
const BrewSlugRoute = rootChild(BrewSlugRouteImport, "/brew/$slug");
const CartRoute = rootChild(CartRouteImport, "/cart");
const CheckoutRoute = rootChild(CheckoutRouteImport, "/checkout");
const CollectionsSlugRoute = rootChild(CollectionsSlugRouteImport, "/collections/$slug");
const CompareSlugRoute = rootChild(CompareSlugRouteImport, "/compare/$slug");
const ContactRoute = rootChild(ContactRouteImport, "/contact");
const DesignSystemRoute = rootChild(DesignSystemRouteImport, "/design-system");
const ForbiddenRoute = rootChild(ForbiddenRouteImport, "/forbidden");
const GuidesSlugRoute = rootChild(GuidesSlugRouteImport, "/guides/$slug");
const OrdersRoute = rootChild(OrdersRouteImport, "/orders");
const OriginsSlugRoute = rootChild(OriginsSlugRouteImport, "/origins/$slug");
const PanelManageRoute = rootChild(PanelManageRouteImport, "/panel/manage");
const PanelRoute = rootChild(PanelRouteImport, "/panel");
const PrivacyRoute = rootChild(PrivacyRouteImport, "/privacy");
const ProductsRoute = rootChild(ProductsRouteImport, "/products");
const ProfileRoute = rootChild(ProfileRouteImport, "/profile");
const QuizRoute = rootChild(QuizRouteImport, "/quiz");
const RoasteriesRoute = rootChild(RoasteriesRouteImport, "/roasteries");
const RobotsDottxtRoute = rootChild(RobotsDottxtRouteImport, "/robots.txt");
const SearchRoute = rootChild(SearchRouteImport, "/search");
const SitemapDotxmlRoute = rootChild(SitemapDotxmlRouteImport, "/sitemap.xml");
const TastesSlugRoute = rootChild(TastesSlugRouteImport, "/tastes/$slug");
const TermsRoute = rootChild(TermsRouteImport, "/terms");

const AuthIndexRoute = AuthIndexRouteImport.update({ id: "/", path: "/", getParentRoute: () => AuthRoute } as any);
const AuthVerifyRoute = AuthVerifyRouteImport.update({ id: "/verify", path: "/verify", getParentRoute: () => AuthRoute } as any);
const BlogIndexRoute = BlogIndexRouteImport.update({ id: "/", path: "/", getParentRoute: () => BlogRoute } as any);
const BlogSlugRoute = BlogSlugRouteImport.update({ id: "/$slug", path: "/$slug", getParentRoute: () => BlogRoute } as any);
const OrdersIndexRoute = OrdersIndexRouteImport.update({ id: "/", path: "/", getParentRoute: () => OrdersRoute } as any);
const OrdersIdRoute = OrdersIdRouteImport.update({ id: "/$id", path: "/$id", getParentRoute: () => OrdersRoute } as any);
const ProductsIndexRoute = ProductsIndexRouteImport.update({ id: "/", path: "/", getParentRoute: () => ProductsRoute } as any);
const ProductsSlugRoute = ProductsSlugRouteImport.update({ id: "/$slug", path: "/$slug", getParentRoute: () => ProductsRoute } as any);
const RoasteriesIndexRoute = RoasteriesIndexRouteImport.update({ id: "/", path: "/", getParentRoute: () => RoasteriesRoute } as any);
const RoasteriesSlugRoute = RoasteriesSlugRouteImport.update({ id: "/$slug", path: "/$slug", getParentRoute: () => RoasteriesRoute } as any);

const AuthRouteWithChildren = AuthRoute._addFileChildren({ AuthIndexRoute, AuthVerifyRoute });
const BlogRouteWithChildren = BlogRoute._addFileChildren({ BlogIndexRoute, BlogSlugRoute });
const OrdersRouteWithChildren = OrdersRoute._addFileChildren({ OrdersIndexRoute, OrdersIdRoute });
const ProductsRouteWithChildren = ProductsRoute._addFileChildren({ ProductsIndexRoute, ProductsSlugRoute });
const RoasteriesRouteWithChildren = RoasteriesRoute._addFileChildren({ RoasteriesIndexRoute, RoasteriesSlugRoute });

export const routeTree = rootRouteImport._addFileChildren({
  IndexRoute,
  AboutRoute,
  AdminContentEditEntryIdRoute,
  AdminContentLinksRoute,
  AdminContentRoute,
  AdminFinanceRoute,
  AdminOperationsRoute,
  AuthRoute: AuthRouteWithChildren,
  BlogRoute: BlogRouteWithChildren,
  BrewSlugRoute,
  CartRoute,
  CheckoutRoute,
  CollectionsSlugRoute,
  CompareSlugRoute,
  ContactRoute,
  DesignSystemRoute,
  ForbiddenRoute,
  GuidesSlugRoute,
  OrdersRoute: OrdersRouteWithChildren,
  OriginsSlugRoute,
  PanelManageRoute,
  PanelRoute,
  PrivacyRoute,
  ProductsRoute: ProductsRouteWithChildren,
  ProfileRoute,
  QuizRoute,
  RoasteriesRoute: RoasteriesRouteWithChildren,
  RobotsDottxtRoute,
  SearchRoute,
  SitemapDotxmlRoute,
  TastesSlugRoute,
  TermsRoute,
});

declare module "@tanstack/react-router" {
  interface FileRoutesByPath {
    "/admin/content-edit/$entryId": { id: "/admin/content-edit/$entryId"; path: "/admin/content-edit/$entryId"; fullPath: "/admin/content-edit/$entryId"; preLoaderRoute: typeof AdminContentEditEntryIdRouteImport; parentRoute: typeof rootRouteImport };
    "/admin/content-links": { id: "/admin/content-links"; path: "/admin/content-links"; fullPath: "/admin/content-links"; preLoaderRoute: typeof AdminContentLinksRouteImport; parentRoute: typeof rootRouteImport };
    "/admin/content": { id: "/admin/content"; path: "/admin/content"; fullPath: "/admin/content"; preLoaderRoute: typeof AdminContentRouteImport; parentRoute: typeof rootRouteImport };
    "/admin/finance": { id: "/admin/finance"; path: "/admin/finance"; fullPath: "/admin/finance"; preLoaderRoute: typeof AdminFinanceRouteImport; parentRoute: typeof rootRouteImport };
    "/admin/operations": { id: "/admin/operations"; path: "/admin/operations"; fullPath: "/admin/operations"; preLoaderRoute: typeof AdminOperationsRouteImport; parentRoute: typeof rootRouteImport };
    "/panel/manage": { id: "/panel/manage"; path: "/panel/manage"; fullPath: "/panel/manage"; preLoaderRoute: typeof PanelManageRouteImport; parentRoute: typeof rootRouteImport };
    "/panel": { id: "/panel"; path: "/panel"; fullPath: "/panel"; preLoaderRoute: typeof PanelRouteImport; parentRoute: typeof rootRouteImport };
    "/brew/$slug": { id: "/brew/$slug"; path: "/brew/$slug"; fullPath: "/brew/$slug"; preLoaderRoute: typeof BrewSlugRouteImport; parentRoute: typeof rootRouteImport };
    "/collections/$slug": { id: "/collections/$slug"; path: "/collections/$slug"; fullPath: "/collections/$slug"; preLoaderRoute: typeof CollectionsSlugRouteImport; parentRoute: typeof rootRouteImport };
    "/compare/$slug": { id: "/compare/$slug"; path: "/compare/$slug"; fullPath: "/compare/$slug"; preLoaderRoute: typeof CompareSlugRouteImport; parentRoute: typeof rootRouteImport };
    "/guides/$slug": { id: "/guides/$slug"; path: "/guides/$slug"; fullPath: "/guides/$slug"; preLoaderRoute: typeof GuidesSlugRouteImport; parentRoute: typeof rootRouteImport };
    "/origins/$slug": { id: "/origins/$slug"; path: "/origins/$slug"; fullPath: "/origins/$slug"; preLoaderRoute: typeof OriginsSlugRouteImport; parentRoute: typeof rootRouteImport };
    "/robots.txt": { id: "/robots.txt"; path: "/robots.txt"; fullPath: "/robots.txt"; preLoaderRoute: typeof RobotsDottxtRouteImport; parentRoute: typeof rootRouteImport };
    "/tastes/$slug": { id: "/tastes/$slug"; path: "/tastes/$slug"; fullPath: "/tastes/$slug"; preLoaderRoute: typeof TastesSlugRouteImport; parentRoute: typeof rootRouteImport };
  }
}
