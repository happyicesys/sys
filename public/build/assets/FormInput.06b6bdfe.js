import{o as t,f as l,b as a,r as n,l as d,n as i,t as u}from"./app.c8734f48.js";const c={for:"text",class:"flex justify-start text-sm font-medium text-gray-700"},m={key:0,class:"text-red-500"},g={class:"mt-1"},y=["type","placeholder","value","disabled","autocomplete"],f={key:0,class:"text-sm text-red-600"},p={__name:"FormInput",props:{autocomplete:{type:String,default:"on"},placeholderStr:{type:[Array,String]},modelValue:[String,Number],error:String,required:{type:[Boolean,String],default:!1},inputType:{type:String,default:"text"},disabled:[Boolean,Object,String,Number]},setup(e){return(o,r)=>(t(),l("div",null,[a("label",c,[n(o.$slots,"default"),e.required?(t(),l("span",m," * ")):d("",!0)]),a("div",g,[a("input",{type:e.inputType,class:i(["shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md",[e.disabled?"bg-gray-200 hover:cursor-not-allowed":""]]),placeholder:e.placeholderStr,onInput:r[0]||(r[0]=s=>o.$emit("update:modelValue",s.target.value)),value:e.modelValue,disabled:e.disabled,autocomplete:e.autocomplete},null,42,y),e.error?(t(),l("div",f,u(e.error),1)):d("",!0)])]))}};export{p as _};
