import{s as d}from"./MultiSelect.vue_vue_type_style_index_1_lang.200ceaf2.js";import{g as n,o as s,f as m,a as f,u as g}from"./app.6c1fd100.js";const p={class:"overflow-visible"},b={__name:"MultiSelect",props:{canClear:Boolean,clear:Boolean,clearOnBlur:{type:[Boolean,String],default:!0},openDirection:{type:String,default:"bottom"},label:String,mode:String,modelValue:[Array,Boolean,Object,String,Number],options:[Array,Object,String],placeholder:String,refreshOptions:Boolean,trackBy:String,valueProp:String,required:{type:[Boolean,String],default:!1},ref:{type:String,default:"multiselect"},max:{type:Number,default:-1}},emits:["update:modelValue","selected"],setup(e,{emit:c}){const t=c,l=n([]),r=e;function i(a){r.mode==="tags"?(l.value.push(a),t("update:modelValue",l.value)):t("update:modelValue",a),t("selected")}function u(a){r.mode==="tags"&&(l.value=l.value.filter(o=>o.id!=a.id),t("update:modelValue",l.value))}return(a,o)=>(s(),m("div",p,[f(g(d),{modelValue:e.modelValue,canClear:e.canClear,canDeselect:!1,label:e.label,mode:e.mode,object:!0,options:e.options,placeholder:e.placeholder,required:e.required,searchable:!0,valueProp:e.valueProp,onSelect:i,onDeselect:u,clear:e.clear,onRefreshOptions:e.refreshOptions,clearOnBlur:e.clearOnBlur,openDirection:e.openDirection,ref:n,max:e.max},null,8,["modelValue","canClear","label","mode","options","placeholder","required","valueProp","clear","onRefreshOptions","clearOnBlur","openDirection","max"])]))}};export{b as _};
