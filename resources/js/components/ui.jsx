// Adapted from shadcn/ui (MIT). See SHADCN_LICENSE.md.
import {
    Slot,
    Popover as PopoverPrimitive,
    Dialog as SheetPrimitive,
} from "radix-ui";
import { cva } from "class-variance-authority";
import { clsx } from "clsx";
import { twMerge } from "tailwind-merge";
import { X } from "lucide-react";

export function cn(...inputs) {
    return twMerge(clsx(inputs));
}
const buttonVariants = cva(
    "ui-button inline-flex shrink-0 items-center justify-center gap-2 rounded-md text-sm font-medium whitespace-nowrap transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 [&_svg]:size-4",
    {
        variants: {
            variant: {
                default:
                    "bg-primary text-primary-foreground hover:bg-primary/90",
                outline:
                    "border border-input bg-background shadow-xs hover:bg-accent",
                ghost: "hover:bg-accent hover:text-accent-foreground",
            },
            size: {
                default: "h-9 px-4 py-2",
                sm: "h-8 px-3 text-xs",
                icon: "size-9",
            },
        },
        defaultVariants: { variant: "default", size: "default" },
    },
);
export function Button({
    asChild = false,
    className,
    variant,
    size,
    ...props
}) {
    const Comp = asChild ? Slot.Root : "button";
    return (
        <Comp
            data-slot="button"
            className={cn(buttonVariants({ variant, size, className }))}
            {...props}
        />
    );
}
export function Card({ className, ...props }) {
    return (
        <section
            data-slot="card"
            className={cn(
                "rounded-xl border bg-card text-card-foreground shadow-xs",
                className,
            )}
            {...props}
        />
    );
}
export function Badge({ className, ...props }) {
    return (
        <span
            data-slot="badge"
            className={cn(
                "inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-medium",
                className,
            )}
            {...props}
        />
    );
}
export const Popover = PopoverPrimitive.Root;
export const PopoverTrigger = PopoverPrimitive.Trigger;
export function PopoverContent({
    className,
    align = "end",
    sideOffset = 8,
    ...props
}) {
    return (
        <PopoverPrimitive.Portal>
            <PopoverPrimitive.Content
                data-slot="popover-content"
                align={align}
                sideOffset={sideOffset}
                className={cn(
                    "z-50 rounded-xl border bg-popover text-popover-foreground shadow-md outline-none",
                    className,
                )}
                {...props}
            />
        </PopoverPrimitive.Portal>
    );
}
export const Sheet = SheetPrimitive.Root;
export const SheetTrigger = SheetPrimitive.Trigger;
export const SheetTitle = SheetPrimitive.Title;
export const SheetDescription = SheetPrimitive.Description;
export function SheetContent({ children, className, ...props }) {
    return (
        <SheetPrimitive.Portal>
            <SheetPrimitive.Overlay className="fixed inset-0 z-50 bg-black/40" />
            <SheetPrimitive.Content
                data-slot="sheet-content"
                className={cn(
                    "fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r bg-background p-4 shadow-lg",
                    className,
                )}
                {...props}
            >
                {children}
                <SheetPrimitive.Close
                    className="absolute right-3 top-3 rounded-md p-2 hover:bg-accent"
                    aria-label="Close navigation"
                >
                    <X className="size-4" />
                </SheetPrimitive.Close>
            </SheetPrimitive.Content>
        </SheetPrimitive.Portal>
    );
}
