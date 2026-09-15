import StoreLayout from "@/Layouts/StoreLayout";
import { money } from "@/lib/store";
import { Head, Link, usePage } from "@inertiajs/react";
import { ArrowRight, Check, Package, Ruler, Truck } from "lucide-react";

const STEPS = ["Bag", "Body profile", "Delivery & payment", "Done"];

const CLOUDINARY_UPLOAD = /^(https?:\/\/res\.cloudinary\.com\/[^/]+\/image\/upload\/)(v\d+\/.+)$/;
const cdn = (url, width) => {
    const m = url && CLOUDINARY_UPLOAD.exec(url);
    return m ? `${m[1]}f_auto,q_auto,w_${width}/${m[2]}` : url;
};

const fmtDate = (iso) => (iso ? new Date(iso).toLocaleDateString(undefined, { day: "numeric", month: "long", year: "numeric" }) : null);

const PAYMENT_LABEL = { cash_on_delivery: "Pay on delivery", bank_transfer: "Bank transfer" };

const Card = ({ className = "", children }) => (
    <div className={`rounded-3xl bg-white shadow-[0_1px_0_rgba(0,0,0,0.04),0_20px_50px_-30px_rgba(0,0,0,0.15)] ${className}`}>{children}</div>
);

/* Horizontal on desktop, vertical on phones. Cancelled orders show a single flat state. */
export const Timeline = ({ order, compact = false }) => {
    if (order.status.value === "cancelled") {
        return <p className="rounded-2xl bg-red-50 px-4 py-3 text-sm text-red-800">{order.status.description}</p>;
    }
    const current = order.status.step;
    return (
        <ol className={`grid gap-4 ${compact ? "grid-cols-3 sm:grid-cols-6" : "sm:grid-cols-6"}`}>
            {order.journey.map((s, i) => {
                const state = i < current ? "done" : i === current ? "current" : "todo";
                return (
                    <li key={s.value} className="flex gap-3 sm:block">
                        <div className="flex items-center sm:mb-3">
                            <span className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold ${state === "done" ? "bg-gray-900 text-white" : state === "current" ? "bg-gray-900 text-white ring-4 ring-gray-900/15" : "bg-gray-200 text-gray-500"}`}>
                                {state === "done" ? <Check className="h-3.5 w-3.5" /> : i + 1}
                            </span>
                            {i < order.journey.length - 1 && <span className={`ml-2 hidden h-px flex-1 sm:block ${state === "done" ? "bg-gray-900" : "bg-gray-200"}`} />}
                        </div>
                        <div>
                            <p className={`text-[13px] font-medium ${state === "todo" ? "text-gray-400" : ""}`}>{s.label}</p>
                            {!compact && state === "current" && <p className="mt-1 text-xs text-gray-500">{s.description}</p>}
                        </div>
                    </li>
                );
            })}
        </ol>
    );
};

export default function Show({ order, placed }) {
    const user = usePage().props.auth?.user ?? null;

    return (
        <StoreLayout steps={placed ? STEPS : null} step={3} secure={placed} back={{ href: user ? route("dashboard") : "/", label: user ? "Your atelier" : "Home" }} wide>
            <Head title={`Order ${order.number}`} />

            <div className="animate-slide-up">
                {placed && (
                    <span className="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-800">
                        <Check className="h-3.5 w-3.5" /> Order placed
                    </span>
                )}
                <div className="mt-4 flex flex-wrap items-end justify-between gap-6">
                    <div>
                        <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-gray-400">Order {order.number}</p>
                        <h1 className="mt-2 text-4xl font-light tracking-tight sm:text-5xl">{placed ? "Thank you. It's in our hands now." : order.status.label}</h1>
                        <p className="mt-3 max-w-xl text-[15px] text-gray-500">
                            {placed ? `A confirmation is on its way to ${order.shipping.name}. ` : ""}
                            {order.status.description}
                        </p>
                    </div>
                    <p className="text-sm text-gray-500">Placed {fmtDate(order.placed_at)}</p>
                </div>
            </div>

            <Card className="mt-10 p-7 animate-slide-up [animation-delay:80ms]">
                <Timeline order={order} />
            </Card>

            <div className="mt-6 grid gap-6 lg:grid-cols-12">
                <Card className="p-7 lg:col-span-7 animate-slide-up [animation-delay:160ms]">
                    <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-gray-400">Your suits</p>
                    <ul className="mt-5 divide-y divide-gray-100">
                        {order.items.map((item) => (
                            <li key={item.id} className="flex gap-5 py-5 first:pt-0 last:pb-0">
                                <span className="relative h-28 w-24 shrink-0 overflow-hidden rounded-2xl bg-[#efece6]">
                                    {item.fabric_image && <img src={cdn(item.fabric_image, 320)} alt="" className="absolute inset-0 h-full w-full object-cover" />}
                                </span>
                                <div className="min-w-0 flex-1">
                                    <div className="flex justify-between gap-4">
                                        <div>
                                            <p className="text-lg font-medium tracking-tight">Tailored suit</p>
                                            <p className="text-sm text-gray-500">{item.fabric_name}{item.quantity > 1 ? ` × ${item.quantity}` : ""}</p>
                                        </div>
                                        <p className="font-medium">{money(item.price * item.quantity)}</p>
                                    </div>
                                    <ul className="mt-3 flex flex-wrap gap-1.5">
                                        {(item.summary || []).map((row) => (
                                            <li key={row.label} className="rounded-full bg-[#f7f6f3] px-2.5 py-1 text-[11px]">
                                                <span className="text-gray-400">{row.label} · </span>
                                                <span className="font-medium text-gray-700">{row.value}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            </li>
                        ))}
                    </ul>
                    <dl className="mt-6 space-y-2 border-t border-gray-100 pt-5 text-sm">
                        <div className="flex justify-between"><dt className="text-gray-500">Subtotal</dt><dd>{money(order.subtotal)}</dd></div>
                        <div className="flex justify-between"><dt className="text-gray-500">Shipping</dt><dd>{order.shipping_cost > 0 ? money(order.shipping_cost) : "Free & tracked"}</dd></div>
                        <div className="flex justify-between border-t border-gray-900 pt-3 text-base"><dt className="font-medium">Total</dt><dd className="text-xl font-light tracking-tight">{money(order.total)}</dd></div>
                    </dl>
                </Card>

                <div className="grid gap-6 lg:col-span-5 animate-slide-up [animation-delay:240ms]">
                    <Card className="p-7">
                        <p className="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-gray-400"><Truck className="h-4 w-4" /> Delivery</p>
                        <p className="mt-4 text-[15px] font-medium">{order.shipping.name}</p>
                        <p className="mt-1 text-sm text-gray-500">
                            {[order.shipping.address, order.shipping.address2].filter(Boolean).join(", ")}<br />
                            {order.shipping.city} {order.shipping.postcode}<br />
                            {order.shipping.country}
                        </p>
                        <p className="mt-3 text-sm text-gray-500">{order.shipping.phone} · {order.email}</p>
                        <dl className="mt-5 grid grid-cols-2 gap-3 border-t border-gray-100 pt-5 text-sm">
                            <div><dt className="text-gray-500">Payment</dt><dd className="font-medium">{PAYMENT_LABEL[order.payment_method] ?? order.payment_method}</dd></div>
                            <div><dt className="text-gray-500">Status</dt><dd className="font-medium capitalize">{order.payment_status}</dd></div>
                        </dl>
                        {order.payment_method === "bank_transfer" && order.payment_status !== "paid" && (
                            <p className="mt-4 rounded-2xl bg-[#f7f6f3] px-4 py-3 text-xs text-gray-600">Our bank details are in your confirmation email. Production begins once the transfer clears.</p>
                        )}
                    </Card>

                    <Card className="p-7">
                        <p className="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-gray-400"><Ruler className="h-4 w-4" /> Body profile</p>
                        <p className="mt-4 text-[15px] font-medium">{order.body_profile.name}</p>
                        <p className="text-sm text-gray-500">{order.body_profile.height_cm} cm · {order.body_profile.weight_kg} kg · {order.body_profile.age} years</p>
                        <dl className="mt-4 grid grid-cols-2 gap-x-6 gap-y-1 text-xs">
                            {Object.entries(order.body_profile.measurements || {}).map(([key, value]) => (
                                <div key={key} className="flex justify-between">
                                    <dt className="text-gray-500 capitalize">{key.replace(/_/g, " ")}</dt>
                                    <dd className="font-medium">{value} cm</dd>
                                </div>
                            ))}
                        </dl>
                    </Card>

                    {!user && (
                        <Card className="bg-[#141414] p-7 text-white">
                            <p className="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-white/50"><Package className="h-4 w-4" /> Track this order</p>
                            <p className="mt-3 text-[15px] text-white/80">Create an account with the same email and this order, with its progress, appears in your atelier.</p>
                            <Link href={route("register")} className="btn-ink btn-ink--light mt-5">
                                Create an account <ArrowRight className="h-4 w-4" />
                            </Link>
                        </Card>
                    )}
                </div>
            </div>
        </StoreLayout>
    );
}
