// TODO: replace with real API call when backend ready.
export type OrderStatus =
  | "pending"
  | "confirmed"
  | "roasting"
  | "shipped"
  | "delivered";

export interface MockOrderItem {
  productName: string;
  weight: number;
  grind: string;
  qty: number;
  price: number;
}

export interface MockOrder {
  id: string;
  roasteryName: string;
  items: MockOrderItem[];
  total: number;
  status: OrderStatus;
  trackingCode?: string;
  createdAt: string;
  estimatedDelivery: string;
}

export const mockOrders: MockOrder[] = [
  {
    id: "10247",
    roasteryName: "روستری امکان",
    items: [
      { productName: "یرگاچف اتیوپی", weight: 250, grind: "دانه", qty: 1, price: 520000 },
      { productName: "کلمبیا هوئیلا", weight: 500, grind: "اسپرسو", qty: 1, price: 860000 },
    ],
    total: 1380000,
    status: "shipped",
    trackingCode: "TP-93842756",
    createdAt: "۱۴۰۴/۰۹/۱۲",
    estimatedDelivery: "۱۴۰۴/۰۹/۱۵",
  },
  {
    id: "10231",
    roasteryName: "قهوه موآ",
    items: [{ productName: "کنیا AA", weight: 250, grind: "دانه", qty: 2, price: 780000 }],
    total: 1560000,
    status: "delivered",
    trackingCode: "TP-93711209",
    createdAt: "۱۴۰۴/۰۹/۰۵",
    estimatedDelivery: "۱۴۰۴/۰۹/۰۸",
  },
  {
    id: "10219",
    roasteryName: "اسکارو",
    items: [
      { productName: "بلند اسپرسو اسکارو", weight: 500, grind: "اسپرسو", qty: 1, price: 820000 },
    ],
    total: 820000,
    status: "roasting",
    createdAt: "۱۴۰۴/۰۹/۱۸",
    estimatedDelivery: "۱۴۰۴/۰۹/۲۲",
  },
  {
    id: "10204",
    roasteryName: "هوفر کافی",
    items: [
      { productName: "سیدامو اتیوپی", weight: 250, grind: "فرنچ‌پرس", qty: 1, price: 640000 },
    ],
    total: 640000,
    status: "confirmed",
    createdAt: "۱۴۰۴/۰۹/۱۹",
    estimatedDelivery: "۱۴۰۴/۰۹/۲۳",
  },
  {
    id: "10198",
    roasteryName: "قهوه لویا",
    items: [{ productName: "لیمو اتیوپی", weight: 1000, grind: "دانه", qty: 1, price: 2400000 }],
    total: 2400000,
    status: "pending",
    createdAt: "۱۴۰۴/۰۹/۲۰",
    estimatedDelivery: "۱۴۰۴/۰۹/۲۵",
  },
];

export const statusLabels: Record<OrderStatus, string> = {
  pending: "ثبت سفارش",
  confirmed: "تایید روستری",
  roasting: "در حال رست",
  shipped: "ارسال شده",
  delivered: "تحویل داده شده",
};

export const statusOrder: OrderStatus[] = [
  "pending",
  "confirmed",
  "roasting",
  "shipped",
  "delivered",
];

export const statusColor: Record<OrderStatus, string> = {
  pending: "#8B6914",
  confirmed: "#C8965A",
  roasting: "#C07A00",
  shipped: "#C8965A",
  delivered: "#4A7C3A",
};
