import{o as t,f as r,b as o,r as n,l,t as u}from"./app.242e5fba.js";const i={for:"text",class:"flex justify-start text-sm font-medium text-gray-700"},c={key:0,class:"text-red-500"},m={class:"mt-1"},f=["placeholder","value","rows"],g={key:0,class:"text-sm text-red-600"},h={__name:"FormTextarea",props:{placeholderStr:{type:String},error:String,modelValue:String,required:{type:[Boolean,String],default:!1},rows:{type:Number,default:5}},setup(e){return(a,s)=>(t(),r("div",null,[o("label",i,[n(a.$slots,"default"),e.required?(t(),r("span",c," * ")):l("",!0)]),o("div",m,[o("textarea",{class:"shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md",placeholder:e.placeholderStr,onInput:s[0]||(s[0]=d=>a.$emit("update:modelValue",d.target.value)),value:e.modelValue,rows:e.rows},null,40,f),e.error?(t(),r("div",g,u(e.error),1)):l("",!0)])]))}};export{h as _};
