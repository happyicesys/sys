import{Z as n}from"./main.46495f95.js";import{o as i,f as r,b as a,r as c,a as s,u}from"./app.242e5fba.js";const d={for:"text",class:"block text-sm font-medium text-gray-700"},f={class:"mt-1"},V={__name:"DatetimePicker",props:{modelValue:[Date,String,Object],minDate:[Date,String,Object],maxDate:[Date,String,Object],enableTimePicker:{type:Boolean,default:!0}},emits:["update:modelValue"],setup(e,{emit:l}){const m=l;function o(t){m("update:modelValue",t)}return(t,D)=>(i(),r("div",null,[a("label",d,[c(t.$slots,"default")]),a("div",f,[s(u(n),{modelValue:e.modelValue,"onUpdate:modelValue":o,format:"yyyy-MM-dd HH:mm",clearable:!1,monthChangeOnScroll:!1,autoApply:"",closeOnAutoApply:!0,minDate:e.minDate,maxDate:e.maxDate,enableTimePicker:e.enableTimePicker},null,8,["modelValue","minDate","maxDate","enableTimePicker"])])]))}};export{V as _};
